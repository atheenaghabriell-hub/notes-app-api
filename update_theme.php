<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $theme = $_POST['theme'] ?? '';

    if (!empty($user_id) && !empty($theme)) {
        $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
        if ($stmt === false) {
            echo json_encode(["status" => "error", "message" => "Failed to prepare theme update"]);
            exit();
        }

        $stmt->bind_param("si", $theme, $user_id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Theme updated successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update theme"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
$conn->close();
?>