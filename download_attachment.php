<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

$attachment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['role'];

if ($attachment_id <= 0) {
    die("Invalid attachment ID");
}

// Fetch attachment with complaint details
$stmt = $conn->prepare("SELECT ca.*, c.complaint_id, c.student_username, c.department_id, c.is_anonymous
                        FROM complaint_attachments ca
                        JOIN complaints c ON ca.complaint_id = c.complaint_id
                        WHERE ca.attachment_id = ?");
$stmt->bind_param("i", $attachment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Attachment not found");
}

$attachment = $result->fetch_assoc();
$stmt->close();

// Security checks
$can_access = false;

if ($role === 'admin') {
    $can_access = true; // Admin can access all attachments
} elseif ($role === 'student') {
    // Students can only access their own attachments
    $can_access = ($attachment['student_username'] === $_SESSION['username']);
} elseif (in_array($role, ['department_officer', 'teacher'])) {
    // Staff can access if complaint belongs to their department or they're a teacher
    if ($role === 'department_officer') {
        $user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
        $user_stmt->bind_param("s", $_SESSION['username']);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_stmt->close();
        
        $can_access = ($user_data && $user_data['department_id'] == $attachment['department_id']);
    } else {
        // Teachers can access all
        $can_access = true;
    }
}

if (!$can_access) {
    die("Unauthorized: You do not have permission to access this file.");
}

// Check if file exists
$file_path = $attachment['file_path'];
if (!file_exists($file_path)) {
    die("File not found on server.");
}

// Set headers for download
$file_name = $attachment['file_name'];
$file_type = $attachment['file_type'];
$file_size = $attachment['file_size'];

header('Content-Type: ' . $file_type);
header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($file_path);
exit;
