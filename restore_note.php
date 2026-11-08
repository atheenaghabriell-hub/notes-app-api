<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    $conn->close();
    exit();
}

$note_id = $_POST['note_id'] ?? '';
if (!ctype_digit((string)$note_id) || (int)$note_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing or invalid note ID"]);
    $conn->close();
    exit();
}

$stmt = $conn->prepare("UPDATE notes SET status = 'active' WHERE id = ?");
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to prepare restore query"]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $note_id);
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Note restored successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to restore note"]);
}

$stmt->close();
$conn->close();
?>
