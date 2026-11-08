<?php
// Tell the app we are returning JSON data
header("Content-Type: application/json");

//Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "notes_app";

// 1. Connect to MySQL database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// 2. Get the date from Android 
$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

if (empty($user) || empty($pass)) {
    echo json_encode(["status" => "error", "message" => "Username and password are required."]);
    exit();
}

// 3. Find the user in the database including theme_preference using prepared statements
$stmt = $conn->prepare("SELECT id, password, theme_preference FROM users WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

// check if user exists
if ($row = $result->fetch_assoc()) {
    $hashed_password_from_db = $row['password'];

    // 4. Verify the password securely
    if (password_verify($pass, $hashed_password_from_db)) {
      // Passwords match !! Send Back Success, user's ID, and theme preference
      echo json_encode([
        "status" => "success",
        "message" => "Login successful.",
        "user_id" => $row['id'],
        "theme_preference" => $row['theme_preference'] ?? 'light' // Default to 'light' kung walang laman
        ]);
    } else {
        // Password is wrong
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
    }
} else {
    // User doesn't exist
    echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
}

// Close Connections
$stmt->close();
$conn->close();
?>