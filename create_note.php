<?php
header("Content-Type: application/json");
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    if (empty($user_id) || empty($title) || empty($content)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all fields"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare note insert"]);
        exit();
    }

    $stmt->bind_param("iss", $user_id, $title, $content);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Note created successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create note"]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}

$conn->close();
?>