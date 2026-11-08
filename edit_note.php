<?php
include 'db.php';

$note_id = $_POST['note_id'] ?? '';
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$category = $_POST['category'] ?? 'Personal';

if (!empty($note_id) && !empty($title) && !empty($content)) {
    $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, category = ? WHERE id = ?");
    $stmt->bind_param("sssi", $title, $content, $category, $note_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Note updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update note"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
}
?>