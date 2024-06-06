<?php
session_start();

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Process the approval or rejection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];

    if (isset($_POST['approve'])) {
        // Approve the teacher
        $sql = "UPDATE users SET approved = 1 WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            // Approval successful
            $stmt->close();
        } else {
            // Display an error message or log the error
            echo "Error approving teacher: " . $stmt->error;
            $stmt->close();
        }
    } elseif (isset($_POST['reject'])) {
        // Reject the teacher (you may also choose to delete the user)
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            // Rejection successful
            $stmt->close();
        } else {
            // Display an error message or log the error
            echo "Error rejecting teacher: " . $stmt->error;
            $stmt->close();
        }
    }

    // Redirect back to approve_teacher.php
    header("Location: approve_teacher.php");
    exit;
}

$conn->close();
?>
