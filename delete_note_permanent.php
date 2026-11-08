<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$note_id = $_POST['note_id'] ?? '';

if (!empty($note_id)) {
    $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare delete query"]);
        exit();
    }

    $stmt->bind_param("i", $note_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Note permanently deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete note"]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing note ID"]);
}

$conn->close();
?>