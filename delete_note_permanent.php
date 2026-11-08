<?php
include 'db.php';

$note_id = $_POST['note_id'] ?? '';

if (!empty($note_id)) {
    $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
    $stmt->bind_param("i", $note_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Note permanently deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete note"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing note ID"]);
}
?>