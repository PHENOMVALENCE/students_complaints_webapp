<?php
session_start();

require __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . base_url('index.php'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];

    $current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    if (!$current_user_id) {
        $current_user_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $current_user_stmt->bind_param("s", $_SESSION['username']);
        $current_user_stmt->execute();
        $current_user_result = $current_user_stmt->get_result();
        $current_user_data = $current_user_result->fetch_assoc();
        $current_user_id = $current_user_data['user_id'];
        $current_user_stmt->close();
    }

    if ($userId == $current_user_id) {
        $_SESSION['message'] = "error|You cannot delete your own account!";
        header("Location: " . base_url('users_management.php'));
        exit;
    }

    $sqlDeleteUser = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sqlDeleteUser);
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "success|User deleted successfully!";
        header("Location: " . base_url('users_management.php'));
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to delete user: " . $stmt->error;
        header("Location: " . base_url('users_management.php'));
        exit;
    }

    $stmt->close();
} else {
    header("Location: " . base_url('users_management.php'));
    exit;
}

$conn->close();
