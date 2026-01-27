<?php
session_start();

require __DIR__ . '/../config/connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    $checkDuplicate = "SELECT user_id FROM users WHERE username = ?";
    $stmtCheck = $conn->prepare($checkDuplicate);
    $stmtCheck->bind_param("s", $username);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows > 0) {
        echo "Error: Username already exists. Choose a different username.";
        $stmtCheck->close();
        $conn->close();
        exit;
    }

    $stmtCheck->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $approvedStatus = ($role === 'teacher') ? 0 : 1;

    $sql = "INSERT INTO users (username, password, role, approved) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo "Error: " . $conn->error;
        $conn->close();
        exit;
    }

    $stmt->bind_param("sssi", $username, $hashed_password, $role, $approvedStatus);

    try {
        $stmt->execute();
        header("Location: " . base_url('index.php'));
        exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

    $stmt->close();
}

$conn->close();
