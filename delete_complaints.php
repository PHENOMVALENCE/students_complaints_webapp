<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $complaintId = (int)$_GET['id'];
    $referer = $_SERVER['HTTP_REFERER'] ?? 'students_complaints.php';

    $sql = "DELETE FROM complaints WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Complaint deleted successfully!";
        // Redirect back to where they came from, or default to complaints page
        if (strpos($referer, 'students_complaints.php') !== false || strpos($referer, 'admin_dashboard.php') !== false) {
            header("Location: students_complaints.php");
        } else {
            header("Location: " . $referer);
        }
    } else {
        $_SESSION['message'] = "error|Failed to delete complaint: " . $conn->error;
        header("Location: students_complaints.php");
    }
    $stmt->close();
    exit;
} else {
    header("Location: students_complaints.php");
    exit;
}
?>
