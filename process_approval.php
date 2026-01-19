<?php
session_start();

// Security Check: Only admins should access this logic
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Process the approval or rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']); // Ensure ID is an integer

    if (isset($_POST['approve'])) {
        // --- APPROVE LOGIC ---
        // Sets 'approved' column to 1 so the teacher can log in
        $sql = "UPDATE users SET approved = 1 WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Teacher approved successfully!";
        } else {
            $_SESSION['message'] = "error|Failed to approve teacher: " . $stmt->error;
        }
        $stmt->close();

    } elseif (isset($_POST['reject'])) {
        // --- REJECT LOGIC ---
        // Removes the unapproved account from the database
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Teacher request rejected and removed.";
        } else {
            $_SESSION['message'] = "error|Failed to reject teacher: " . $stmt->error;
        }
        $stmt->close();
    }

    // Redirect back to the approval list page
    header("Location: teacher_approval.php");
    exit;
} else {
    // If someone tries to access this file directly without POST data
    header("Location: teacher_approval.php");
    exit;
}

$conn->close();
?>