<?php
header('Content-Type: application/json; charset=utf-8');

$response = [
    'status' => 'success',
    'message' => 'Notes App API is reachable and running!',
    'server_ip' => '192.168.18.18',
    'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'database' => 'disconnected'
];

// Test database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "notes_app";

try {
    $conn = @new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        $response['database'] = 'error: ' . $conn->connect_error;
    } else {
        $response['database'] = 'connected to ' . $dbname;
        
        // Count users and notes
        $uRes = $conn->query("SELECT COUNT(*) AS total FROM users");
        $nRes = $conn->query("SELECT COUNT(*) AS total FROM notes");
        $response['stats'] = [
            'total_users' => $uRes ? (int)$uRes->fetch_assoc()['total'] : 0,
            'total_notes' => $nRes ? (int)$nRes->fetch_assoc()['total'] : 0
        ];
        
        $conn->close();
    }
} catch (Throwable $e) {
    $response['database'] = 'error: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
