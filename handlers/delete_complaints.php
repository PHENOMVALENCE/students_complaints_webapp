<?php
session_start();

require __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . base_url('index.php'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $complaintId = (int)$_GET['id'];
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    $sql = "DELETE FROM complaints WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaintId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Complaint deleted successfully!";
        if (strpos($referer, 'students_complaints.php') !== false || strpos($referer, 'admin_dashboard.php') !== false) {
            header("Location: " . base_url('students_complaints.php'));
        } elseif (!empty($referer)) {
            header("Location: " . $referer);
        } else {
            header("Location: " . base_url('students_complaints.php'));
        }
    } else {
        $_SESSION['message'] = "error|Failed to delete complaint: " . $conn->error;
        header("Location: " . base_url('students_complaints.php'));
    }
    $stmt->close();
    exit;
} else {
    header("Location: " . base_url('students_complaints.php'));
    exit;
}
