<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

$user_id = trim($_POST['user_id'] ?? '');
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$category = trim($_POST['category'] ?? 'Personal');
$note_status = trim($_POST['status'] ?? 'active');
$client_id = trim($_POST['client_id'] ?? '');

if (!ctype_digit($user_id) || (int)$user_id <= 0 || $title === '' || $content === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please fill in all fields"]);
    exit();
}

if (!in_array($note_status, ['active', 'archived'], true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid note status"]);
    exit();
}

if ($client_id !== '' && (strlen($client_id) > 64 || !preg_match('/^[A-Za-z0-9:-]+$/', $client_id))) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid client note ID"]);
    exit();
}

if ($client_id !== '') {
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
        exit();
    }

    $conn->begin_transaction();
    try {
        $lookup = $conn->prepare("SELECT note_id FROM note_sync_map WHERE user_id = ? AND client_id = ? FOR UPDATE");
        if ($lookup === false) {
            throw new RuntimeException("Failed to prepare note sync lookup");
        }
        $lookup->bind_param("is", $user_id, $client_id);
        if (!$lookup->execute()) {
            throw new RuntimeException("Failed to look up note sync ID");
        }
        $existing = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if ($existing) {
            $note_id = (int)$existing['note_id'];
            $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, category = ?, status = ? WHERE id = ? AND user_id = ?");
            if ($stmt === false) {
                throw new RuntimeException("Failed to prepare note update");
            }
            $stmt->bind_param("ssssii", $title, $content, $category, $note_status, $note_id, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, category, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) {
                throw new RuntimeException("Failed to prepare note insert");
            }
            $stmt->bind_param("issss", $user_id, $title, $content, $category, $note_status);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException("Failed to save note");
        }
        if (!$existing) {
            $note_id = $conn->insert_id;
            $map = $conn->prepare("INSERT INTO note_sync_map (user_id, client_id, note_id) VALUES (?, ?, ?)");
            if ($map === false) {
                throw new RuntimeException("Failed to prepare note sync mapping");
            }
            $map->bind_param("isi", $user_id, $client_id, $note_id);
            if (!$map->execute()) {
                throw new RuntimeException("Failed to save note sync mapping");
            }
            $map->close();
        }

        $stmt->close();
        $conn->commit();
        echo json_encode([
            "status" => "success",
            "message" => "Note created successfully",
            "note_id" => $note_id
        ]);
    } catch (mysqli_sql_exception | RuntimeException $e) {
        $conn->rollback();
        error_log('Note sync failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to sync note"]);
    }

    $conn->close();
    exit();
}

$stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, category, status) VALUES (?, ?, ?, ?, ?)");
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to prepare note insert"]);
    $conn->close();
    exit();
}

$stmt->bind_param("issss", $user_id, $title, $content, $category, $note_status);
if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Note created successfully",
        "note_id" => $conn->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to create note"]);
}

$stmt->close();
$conn->close();
?>
