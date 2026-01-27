<?php
session_start();

require __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: " . base_url('index.php'));
    exit;
}

$attachment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['role'];

if ($attachment_id <= 0) {
    die("Invalid attachment ID");
}

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

$can_access = false;

if ($role === 'admin') {
    $can_access = true;
} elseif ($role === 'student') {
    $can_access = ($attachment['student_username'] === $_SESSION['username']);
} elseif (in_array($role, ['department_officer', 'teacher'])) {
    if ($role === 'department_officer') {
        $user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
        $user_stmt->bind_param("s", $_SESSION['username']);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_stmt->close();
        $can_access = ($user_data && $user_data['department_id'] == $attachment['department_id']);
    } else {
        $can_access = true;
    }
}

if (!$can_access) {
    die("Unauthorized: You do not have permission to access this file.");
}

$rel = $attachment['file_path'];
$file_path = (strpos($rel, '/') === 0 || preg_match('#^[a-zA-Z]:#', $rel)) ? $rel : (APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
if (!file_exists($file_path)) {
    die("File not found on server.");
}

$file_name = $attachment['file_name'];
$file_type = $attachment['file_type'];
$file_size = $attachment['file_size'];

header('Content-Type: ' . $file_type);
header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($file_path);
exit;
