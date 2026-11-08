<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$user_id = $_GET['user_id'] ?? '';
$status = $_GET['status'] ?? 'active';

if (!empty($user_id)) {
    $stmt = $conn->prepare("SELECT id, title, content, create_at, category, status FROM notes WHERE user_id = ? AND status = ? ORDER BY id DESC");
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare notes query"]);
        exit();
    }

    $stmt->bind_param("is", $user_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();

    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }

    echo json_encode(["status" => "success", "notes" => $notes]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid User ID"]);
}

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>