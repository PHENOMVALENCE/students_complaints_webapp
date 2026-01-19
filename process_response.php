<?php
session_start();

// Security Check - Allow both admin and teacher roles
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
    $response = isset($_POST['response']) ? trim($_POST['response']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'resolved';
    
    // Validate complaint ID
    if ($complaintId <= 0) {
        $_SESSION['message'] = "error|Invalid complaint ID.";
        header("Location: " . ($_SESSION['role'] === 'admin' ? 'students_complaints.php' : 'teacher_dashboard.php'));
        exit;
    }
    
    // Check if complaint exists
    $check_stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $check_stmt->bind_param("i", $complaintId);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $_SESSION['message'] = "error|Complaint not found.";
        header("Location: " . ($_SESSION['role'] === 'admin' ? 'students_complaints.php' : 'teacher_dashboard.php'));
        exit;
    }
    
    $old_status_data = $check_result->fetch_assoc();
    $old_status = $old_status_data['status'] ?? 'pending';
    $check_stmt->close();
    
    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'resolved', 'denied'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'resolved';
    }
    
    // For resolved status, require a response
    if ($status === 'resolved' && empty($response)) {
        $_SESSION['message'] = "error|Please provide a response when resolving a complaint.";
        header("Location: " . ($_SESSION['role'] === 'admin' ? 'respond_complaints.php?id=' . $complaintId : 'teacher_dashboard.php'));
        exit;
    }
    
    // Update complaint
    $update_stmt = $conn->prepare("UPDATE complaints SET status = ?, response = ?, updated_at = NOW() WHERE complaint_id = ?");
    $update_stmt->bind_param("ssi", $status, $response, $complaintId);
    
    if ($update_stmt->execute()) {
        // Log in history
        $action = $status === 'resolved' ? 'resolved' : ($status === 'denied' ? 'denied' : 'updated');
        $notes = $status === 'resolved' 
            ? 'Complaint resolved by ' . $_SESSION['role'] 
            : ($status === 'denied' 
                ? 'Complaint denied by ' . $_SESSION['role'] 
                : 'Status updated by ' . $_SESSION['role']);
        
        $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $history_stmt->bind_param("issss", $complaintId, $action, $_SESSION['username'], $old_status, $status, $notes);
        $history_stmt->execute();
        $history_stmt->close();
        
        $_SESSION['message'] = "success|Complaint " . ($status === 'resolved' ? 'resolved' : 'updated') . " successfully!";
        
        // Redirect based on role
        if ($_SESSION['role'] === 'admin') {
            header("Location: students_complaints.php");
        } else {
            header("Location: teacher_dashboard.php");
        }
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to update complaint: " . $update_stmt->error;
        header("Location: " . ($_SESSION['role'] === 'admin' ? 'respond_complaints.php?id=' . $complaintId : 'teacher_dashboard.php'));
        exit;
    }
    
    $update_stmt->close();
} else {
    // Redirect if accessed directly without POST
    header("Location: " . (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' ? 'students_complaints.php' : 'index.php'));
    exit;
}

$conn->close();
?>
