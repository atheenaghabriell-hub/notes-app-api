<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

if (empty($user) || empty($pass)) {
    echo json_encode(["status" => "error", "message" => "Username and password are required."]);
    exit();
}

$hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
if ($stmt === false) {
    echo json_encode(["status" => "error", "message" => "Error registering user."]);
    exit();
}

$stmt->bind_param("ss", $user, $hashedPassword);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "User registered successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Error registering user."]);
}

$stmt->close();
$conn->close();
?>