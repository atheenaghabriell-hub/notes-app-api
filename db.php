<?php
if (!headers_sent()) {
    header('Content-Type: application/json');
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "notes_app";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed.'
    ]);
    exit();
}
