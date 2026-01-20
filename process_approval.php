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
    $user_id = (int) $_POST['user_id'];
    if ($user_id <= 0) {
        $_SESSION['message'] = "error|Invalid user.";
        header("Location: teacher_approval.php");
        exit;
    }

    if (isset($_POST['approve'])) {
        // Only approve teachers that are currently unapproved
        $sql = "UPDATE users SET approved = 1 WHERE user_id = ? AND role = 'teacher' AND (approved = 0 OR approved IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "success|Teacher approved successfully!";
            } else {
                $_SESSION['message'] = "error|Teacher not found or already approved.";
            }
        } else {
            $_SESSION['message'] = "error|Failed to approve teacher: " . $stmt->error;
        }
        $stmt->close();

    } elseif (isset($_POST['reject'])) {
        // Only delete unapproved teacher accounts
        $sql = "DELETE FROM users WHERE user_id = ? AND role = 'teacher' AND (approved = 0 OR approved IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "success|Teacher request rejected and removed.";
            } else {
                $_SESSION['message'] = "error|Teacher not found or already processed.";
            }
        } else {
            $_SESSION['message'] = "error|Failed to reject teacher: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "error|Invalid action.";
    }

    header("Location: teacher_approval.php");
    exit;
} else {
    header("Location: teacher_approval.php");
    exit;
}

$conn->close();
?>