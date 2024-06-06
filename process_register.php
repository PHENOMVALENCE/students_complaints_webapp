<?php
session_start();

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Check for duplicate username
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

    // Check password confirmation (assuming you have a confirm_password field)


    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Set approved status to 0 for teachers
    $approvedStatus = ($role === 'teacher') ? 0 : 1;

    // Insert the user into the database with the appropriate approval status
    $sql = "INSERT INTO users (username, password, role, approved) VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo "Error: " . $conn->error;
        $conn->close();
        exit;
    }

    // Bind parameters
    $stmt->bind_param("sssi", $username, $hashed_password, $role, $approvedStatus);

    try {
        // Execute the statement
        $stmt->execute();
        
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

    $stmt->close();
}

$conn->close();
?>
