<?php
header("Content-Type: application/json");
require_once __DIR__ . '/db.php';

$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

if (empty($user) || empty($pass)) {
    echo json_encode(["status" => "error", "message" => "Username and password are required."]);
    exit();
}

$stmt = $conn->prepare("SELECT id, password, theme_preference FROM users WHERE username = ?");
if ($stmt === false) {
    echo json_encode(["status" => "error", "message" => "Failed to prepare login query."]);
    exit();
}

$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $hashed_password_from_db = $row['password'];

    if (password_verify($pass, $hashed_password_from_db)) {
      echo json_encode([
        "status" => "success",
        "message" => "Login successful.",
        "user_id" => $row['id'],
        "theme_preference" => $row['theme_preference'] ?? 'light'
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
}

$stmt->close();
$conn->close();
?>