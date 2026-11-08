<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$note_id = $_POST['note_id'] ?? '';

if (!empty($note_id)) {
    $stmt = $conn->prepare("UPDATE notes SET status = 'archived' WHERE id = ?");
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare archive query"]);
        exit();
    }

    $stmt->bind_param("i", $note_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Note moved to archive"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to archive note"]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing note ID"]);
}

$conn->close();
?>