<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/db.php';

$user = trim($_POST['username'] ?? $_POST['email'] ?? '');
$pass = $_POST['password'] ?? '';

if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Username and password are required."
    ]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id, password, theme_preference FROM users WHERE username = ? OR email = ?");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to prepare login query."
        ]);
        exit();
    }

    $stmt->bind_param("ss", $user, $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $hashed_password_from_db = $row['password'];

        if (password_verify($pass, $hashed_password_from_db)) {
            echo json_encode([
                "status" => "success",
                "message" => "Login successful.",
                "user_id" => (int)$row['id'],
                "theme_preference" => $row['theme_preference'] ?? 'light'
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Invalid username or password."
            ]);
        }
    } else {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid username or password."
        ]);
    }

    $stmt->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
