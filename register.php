<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$user = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$fullName = trim($_POST['full_name'] ?? $_POST['name'] ?? '');
$pass = $_POST['password'] ?? '';

// Fallback if username wasn't sent directly but email or full name was
if ($user === '') {
    $user = ($email !== '') ? $email : $fullName;
}
if ($email === '' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
    $email = $user;
}

if ($user === '' || $pass === '') {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Username and password are required."
    ]);
    exit();
}

try {
    // Check if user or email already exists
    $checkSql = "SELECT id FROM users WHERE username = ?" . (!empty($email) ? " OR (email IS NOT NULL AND email = ?)" : "");
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        if (!empty($email)) {
            $checkStmt->bind_param("ss", $user, $email);
        } else {
            $checkStmt->bind_param("s", $user);
        }
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Username or email is already registered."
            ]);
            $checkStmt->close();
            $conn->close();
            exit();
        }
        $checkStmt->close();
    }

    $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error preparing user registration."
        ]);
        exit();
    }

    $stmt->bind_param("ssss", $user, $hashedPassword, $email, $fullName);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "User registered successfully.",
            "user_id" => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error registering user."
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
