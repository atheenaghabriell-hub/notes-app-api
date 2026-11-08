<?php
header('Content-Type: application/json');
include 'db_connect.php'; // Siguraduhing tama ang file ng koneksyon mo sa database

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $theme = $_POST['theme'] ?? '';

    if (!empty($user_id) && !empty($theme)) {
        $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
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