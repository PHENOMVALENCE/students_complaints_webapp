<?php
session_start();

require __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . base_url('index.php'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];
    if ($user_id <= 0) {
        $_SESSION['message'] = "error|Invalid user.";
        header("Location: " . base_url('teacher_approval.php'));
        exit;
    }

    if (isset($_POST['approve'])) {
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

    header("Location: " . base_url('teacher_approval.php'));
    exit;
} else {
    header("Location: " . base_url('teacher_approval.php'));
    exit;
}

$conn->close();
