<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    $conn->close();
    exit();
}

$rawBody = file_get_contents('php://input');
try {
    $payload = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Request body must be valid JSON"]);
    $conn->close();
    exit();
}

if ($payload instanceof stdClass && property_exists($payload, 'notes')) {
    $payload = $payload->notes;
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Request body must be an array of notes"]);
    $conn->close();
    exit();
}

$notes = [];
foreach ($payload as $index => $note) {
    if (!$note instanceof stdClass) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Each note must be an object", "index" => $index]);
        $conn->close();
        exit();
    }
    $note = get_object_vars($note);

    $userId = $note['user_id'] ?? '';
    $title = $note['title'] ?? null;
    $content = $note['content'] ?? null;
    $category = $note['category'] ?? 'Personal';
    $status = $note['status'] ?? 'active';
    $serverId = $note['id'] ?? null;
    $clientId = $note['client_id'] ?? $note['unique_id'] ?? null;
    $createdAt = $note['created_at'] ?? $note['create_at'] ?? null;

    if (is_string($serverId) && !ctype_digit($serverId)) {
        if ($clientId === null) {
            $clientId = $serverId;
        }
        $serverId = null;
    }

    if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Each note requires a valid user_id", "index" => $index]);
        $conn->close();
        exit();
    }
    if ((int)$userId <= 0 || !is_string($title) || trim($title) === ''
            || !is_string($content) || trim($content) === ''
            || !is_string($category) || !is_string($status)
            || !in_array($status, ['active', 'archived'], true)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Note has missing or invalid fields", "index" => $index]);
        $conn->close();
        exit();
    }
    if ($serverId !== null && (!is_int($serverId) && !(is_string($serverId) && ctype_digit($serverId))
            || (int)$serverId <= 0)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid note id", "index" => $index]);
        $conn->close();
        exit();
    }
    if ($clientId !== null && (!is_string($clientId) || $clientId === ''
            || strlen($clientId) > 64 || !preg_match('/^[A-Za-z0-9:-]+$/', $clientId))) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid unique note id", "index" => $index]);
        $conn->close();
        exit();
    }
    if ($serverId === null && $clientId === null) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Each note requires id or client_id", "index" => $index]);
        $conn->close();
        exit();
    }
    if ($createdAt !== null && !is_string($createdAt)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid created_at value", "index" => $index]);
        $conn->close();
        exit();
    }

    $notes[] = [
        'user_id' => (int)$userId,
        'title' => trim($title),
        'content' => trim($content),
        'category' => $category,
        'status' => $status,
        'id' => $serverId === null ? null : (int)$serverId,
        'client_id' => $clientId,
        'created_at' => $createdAt
    ];
}

$createMapTable = "CREATE TABLE IF NOT EXISTS note_sync_map (
    user_id INT NOT NULL,
    client_id VARCHAR(64) NOT NULL,
    note_id INT NOT NULL,
    PRIMARY KEY (user_id, client_id),
    UNIQUE KEY unique_note_sync_id (note_id)
) ENGINE=InnoDB";

if (!$conn->query($createMapTable)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Could not initialize note sync"]);
    $conn->close();
    exit();
}

$conn->begin_transaction();
try {
    $syncedNotes = [];
    foreach ($notes as $note) {
        $noteId = null;
        $mappingExists = false;

        if ($note['client_id'] !== null) {
            $lookup = $conn->prepare(
                "SELECT note_id FROM note_sync_map WHERE user_id = ? AND client_id = ? FOR UPDATE"
            );
            if ($lookup === false) {
                throw new RuntimeException("Failed to prepare unique ID lookup");
            }
            $lookup->bind_param("is", $note['user_id'], $note['client_id']);
            if (!$lookup->execute()) {
                throw new RuntimeException("Failed to look up unique note ID");
            }
            $existing = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if ($existing) {
                $mappedId = (int)$existing['note_id'];
                $lookup = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ? FOR UPDATE");
                if ($lookup === false) {
                    throw new RuntimeException("Failed to prepare mapped note lookup");
                }
                $lookup->bind_param("ii", $mappedId, $note['user_id']);
                if (!$lookup->execute()) {
                    throw new RuntimeException("Failed to verify mapped note");
                }
                $mappedNote = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if ($mappedNote) {
                    $noteId = $mappedId;
                    $mappingExists = true;
                } else {
                    $removeMapping = $conn->prepare(
                        "DELETE FROM note_sync_map WHERE user_id = ? AND client_id = ?"
                    );
                    if ($removeMapping === false) {
                        throw new RuntimeException("Failed to prepare stale mapping cleanup");
                    }
                    $removeMapping->bind_param("is", $note['user_id'], $note['client_id']);
                    if (!$removeMapping->execute()) {
                        throw new RuntimeException("Failed to remove stale note mapping");
                    }
                    $removeMapping->close();
                }
            }
        }

        if ($noteId === null && $note['id'] !== null) {
            $lookup = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ? FOR UPDATE");
            if ($lookup === false) {
                throw new RuntimeException("Failed to prepare note lookup");
            }
            $lookup->bind_param("ii", $note['id'], $note['user_id']);
            if (!$lookup->execute()) {
                throw new RuntimeException("Failed to look up note");
            }
            $existing = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if ($existing) {
                $noteId = (int)$existing['id'];
            }
        }

        if ($noteId !== null) {
            if ($note['created_at'] !== null) {
                $stmt = $conn->prepare(
                    "UPDATE notes SET title = ?, content = ?, category = ?, status = ?, create_at = ? WHERE id = ? AND user_id = ?"
                );
                if ($stmt === false) {
                    throw new RuntimeException("Failed to prepare note update");
                }
                $stmt->bind_param(
                    "sssssii",
                    $note['title'],
                    $note['content'],
                    $note['category'],
                    $note['status'],
                    $note['created_at'],
                    $noteId,
                    $note['user_id']
                );
            } else {
                $stmt = $conn->prepare(
                    "UPDATE notes SET title = ?, content = ?, category = ?, status = ? WHERE id = ? AND user_id = ?"
                );
                if ($stmt === false) {
                    throw new RuntimeException("Failed to prepare note update");
                }
                $stmt->bind_param(
                    "ssssii",
                    $note['title'],
                    $note['content'],
                    $note['category'],
                    $note['status'],
                    $noteId,
                    $note['user_id']
                );
            }
            if (!$stmt->execute()) {
                throw new RuntimeException("Failed to update note");
            }
            $stmt->close();
        } else {
            if ($note['created_at'] !== null) {
                $stmt = $conn->prepare(
                    "INSERT INTO notes (user_id, title, content, category, status, create_at) VALUES (?, ?, ?, ?, ?, ?)"
                );
                if ($stmt === false) {
                    throw new RuntimeException("Failed to prepare note insert");
                }
                $stmt->bind_param(
                    "isssss",
                    $note['user_id'],
                    $note['title'],
                    $note['content'],
                    $note['category'],
                    $note['status'],
                    $note['created_at']
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO notes (user_id, title, content, category, status) VALUES (?, ?, ?, ?, ?)"
                );
                if ($stmt === false) {
                    throw new RuntimeException("Failed to prepare note insert");
                }
                $stmt->bind_param(
                    "issss",
                    $note['user_id'],
                    $note['title'],
                    $note['content'],
                    $note['category'],
                    $note['status']
                );
            }
            if (!$stmt->execute()) {
                throw new RuntimeException("Failed to insert note");
            }
            $noteId = (int)$conn->insert_id;
            $stmt->close();

        }

        if ($note['client_id'] !== null && !$mappingExists) {
            $mapping = $conn->prepare(
                "INSERT INTO note_sync_map (user_id, client_id, note_id) VALUES (?, ?, ?)"
            );
            if ($mapping === false) {
                throw new RuntimeException("Failed to prepare unique ID mapping");
            }
            $mapping->bind_param("isi", $note['user_id'], $note['client_id'], $noteId);
            if (!$mapping->execute()) {
                throw new RuntimeException("Failed to save unique ID mapping");
            }
            $mapping->close();
        }

        $syncedNotes[] = [
            "id" => $note['id'],
            "client_id" => $note['client_id'],
            "note_id" => $noteId
        ];
    }

    $conn->commit();
    echo json_encode([
        "status" => "success",
        "message" => "Notes synced successfully",
        "synced_count" => count($syncedNotes),
        "notes" => $syncedNotes
    ]);
} catch (mysqli_sql_exception | RuntimeException $e) {
    $conn->rollback();
    error_log('Notes sync failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to sync notes"]);
}

$conn->close();
?>
