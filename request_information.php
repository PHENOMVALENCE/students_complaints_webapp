<?php
session_start();

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'department_officer', 'teacher'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complaint_id']) && isset($_POST['request_message'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $request_message = trim($_POST['request_message']);
    $requested_by = $_SESSION['username'];
    
    if (empty($request_message)) {
        $_SESSION['message'] = "error|Request message cannot be empty.";
        header("Location: view_complaint_detail.php?id=" . $complaint_id);
        exit;
    }
    
    // Verify complaint exists and user has access
    $check_stmt = $conn->prepare("SELECT department_id, status FROM complaints WHERE complaint_id = ?");
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
    
    // Department officers can only request info for their department's complaints
    if ($_SESSION['role'] === 'department_officer') {
        $user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
        $user_stmt->bind_param("s", $requested_by);
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
    
    // Insert information request
    $stmt = $conn->prepare("INSERT INTO information_requests (complaint_id, requested_by, request_message, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("iss", $complaint_id, $requested_by, $request_message);
    
    if ($stmt->execute()) {
        // Update complaint status to 'awaiting_student_response'
        $update_stmt = $conn->prepare("UPDATE complaints SET status = 'awaiting_student_response', updated_at = NOW() WHERE complaint_id = ?");
        $update_stmt->bind_param("i", $complaint_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Log in history
        $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, 'information_requested', ?, ?, 'awaiting_student_response', ?)");
        $notes = "Information requested from student";
        $history_stmt->bind_param("isss", $complaint_id, $requested_by, $complaint_data['status'], $notes);
        $history_stmt->execute();
        $history_stmt->close();
        
        $_SESSION['message'] = "success|Information request sent to student successfully.";
    } else {
        $_SESSION['message'] = "error|Failed to send request: " . $stmt->error;
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
