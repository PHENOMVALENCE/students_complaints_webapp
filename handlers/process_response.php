<?php
session_start();

require __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header("Location: " . base_url('index.php'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
    $response = isset($_POST['response']) ? trim($_POST['response']) : '';
    $action = isset($_POST['action']) ? $_POST['action'] : 'resolve';
    $status = ($action === 'deny') ? 'denied' : 'resolved';
    
    if ($complaintId <= 0) {
        $_SESSION['message'] = "error|Invalid complaint ID.";
        header("Location: " . base_url($_SESSION['role'] === 'admin' ? 'students_complaints.php' : 'teacher_dashboard.php'));
        exit;
    }
    
    $check_stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $check_stmt->bind_param("i", $complaintId);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $_SESSION['message'] = "error|Complaint not found.";
        header("Location: " . base_url($_SESSION['role'] === 'admin' ? 'students_complaints.php' : 'teacher_dashboard.php'));
        exit;
    }
    
    $old_status_data = $check_result->fetch_assoc();
    $old_status = $old_status_data['status'] ?? 'pending';
    $check_stmt->close();
    
    $valid_statuses = ['pending', 'in_progress', 'resolved', 'denied'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'resolved';
    }
    
    if (in_array($status, ['resolved', 'denied']) && empty($response)) {
        $_SESSION['message'] = "error|Please provide a response when resolving or denying a complaint.";
        header("Location: " . base_url($_SESSION['role'] === 'admin' ? 'respond_complaints.php?id=' . $complaintId : 'teacher_dashboard.php'));
        exit;
    }
    
    $update_stmt = $conn->prepare("UPDATE complaints SET status = ?, response = ?, updated_at = NOW() WHERE complaint_id = ?");
    $update_stmt->bind_param("ssi", $status, $response, $complaintId);
    
    if ($update_stmt->execute()) {
        $action = $status === 'resolved' ? 'resolved' : ($status === 'denied' ? 'denied' : 'updated');
        $notes = $status === 'resolved' 
            ? 'Complaint resolved by ' . $_SESSION['role'] 
            : ($status === 'denied' 
                ? 'Complaint denied by ' . $_SESSION['role'] 
                : 'Status updated by ' . $_SESSION['role']);
        
        $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $history_stmt->bind_param("isssss", $complaintId, $action, $_SESSION['username'], $old_status, $status, $notes);
        $history_stmt->execute();
        $history_stmt->close();
        
        $_SESSION['message'] = "success|Complaint " . ($status === 'resolved' ? 'resolved' : ($status === 'denied' ? 'denied' : 'updated')) . " successfully!";
        
        if ($_SESSION['role'] === 'admin') {
            header("Location: " . base_url('students_complaints.php'));
        } else {
            header("Location: " . base_url('teacher_dashboard.php'));
        }
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to update complaint: " . $update_stmt->error;
        header("Location: " . base_url($_SESSION['role'] === 'admin' ? 'respond_complaints.php?id=' . $complaintId : 'teacher_dashboard.php'));
        exit;
    }
    
    $update_stmt->close();
} else {
    header("Location: " . base_url((isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'students_complaints.php' : 'index.php'));
    exit;
}

$conn->close();
