<?php
session_start();

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'department_officer', 'teacher'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complaint_id']) && isset($_POST['note_text'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $note_text = trim($_POST['note_text']);
    $created_by = $_SESSION['username'];
    
    if (empty($note_text)) {
        $_SESSION['message'] = "error|Note cannot be empty.";
        header("Location: view_complaint_detail.php?id=" . $complaint_id);
        exit;
    }
    
    // Verify complaint exists and user has access
    $check_stmt = $conn->prepare("SELECT department_id FROM complaints WHERE complaint_id = ?");
    $check_stmt->bind_param("i", $complaint_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $_SESSION['message'] = "error|Complaint not found.";
        header("Location: " . ($_SESSION['role'] === 'admin' ? 'students_complaints.php' : ($_SESSION['role'] === 'teacher' ? 'teacher_dashboard.php' : 'department_officer_dashboard.php')));
        exit;
    }
    
    $complaint_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Department officers can only add notes to their department's complaints
    if ($_SESSION['role'] === 'department_officer') {
        $user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
        $user_stmt->bind_param("s", $created_by);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if (!$user_data || $user_data['department_id'] != $complaint_data['department_id']) {
            $_SESSION['message'] = "error|Unauthorized: This complaint does not belong to your department.";
            header("Location: department_officer_dashboard.php");
            exit;
        }
    }
    
    // Insert note
    $stmt = $conn->prepare("INSERT INTO collaboration_notes (complaint_id, created_by, note_text, is_internal) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("iss", $complaint_id, $created_by, $note_text);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Collaboration note added successfully.";
    } else {
        $_SESSION['message'] = "error|Failed to add note: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: view_complaint_detail.php?id=" . $complaint_id);
    exit;
} else {
    header("Location: index.php");
    exit;
}

$conn->close();
?>
