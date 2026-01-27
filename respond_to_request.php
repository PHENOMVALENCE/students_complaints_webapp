<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id']) && isset($_POST['response'])) {
    $request_id = (int)$_POST['request_id'];
    $response = trim($_POST['response']);
    $student_username = $_SESSION['username'];
    
    if (empty($response)) {
        $_SESSION['message'] = "error|Response cannot be empty.";
        header("Location: track_complaints.php");
        exit;
    }
    
    // Verify request exists and belongs to student's complaint
    $check_stmt = $conn->prepare("SELECT ir.*, c.student_username, c.complaint_id FROM information_requests ir JOIN complaints c ON ir.complaint_id = c.complaint_id WHERE ir.request_id = ?");
    $check_stmt->bind_param("i", $request_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $_SESSION['message'] = "error|Request not found.";
        header("Location: track_complaints.php");
        exit;
    }
    
    $request_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($request_data['student_username'] !== $student_username) {
        $_SESSION['message'] = "error|Unauthorized: You can only respond to requests for your own complaints.";
        header("Location: track_complaints.php");
        exit;
    }
    
    if ($request_data['status'] !== 'pending') {
        $_SESSION['message'] = "error|This request has already been responded to.";
        header("Location: view_complaint_detail.php?id=" . $request_data['complaint_id']);
        exit;
    }
    
    // Update request with response
    $stmt = $conn->prepare("UPDATE information_requests SET student_response = ?, status = 'responded', responded_at = NOW() WHERE request_id = ?");
    $stmt->bind_param("si", $response, $request_id);
    
    if ($stmt->execute()) {
        $complaint_id = $request_data['complaint_id'];
        
        // Only revert complaint status to 'pending' when no other pending requests remain
        $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM information_requests WHERE complaint_id = ? AND status = 'pending'");
        $check_stmt->bind_param("i", $complaint_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $still_pending = $check_result->fetch_assoc()['cnt'] > 0;
        $check_stmt->close();
        
        if (!$still_pending) {
            $update_stmt = $conn->prepare("UPDATE complaints SET status = 'pending', updated_at = NOW() WHERE complaint_id = ?");
            $update_stmt->bind_param("i", $complaint_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Log in history
        $new_status = $still_pending ? 'awaiting_student_response' : 'pending';
        $notes = $still_pending ? 'Student responded to one information request; more pending.' : 'Student provided requested information.';
        $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, 'information_provided', ?, 'awaiting_student_response', ?, ?)");
        $history_stmt->bind_param("isss", $complaint_id, $student_username, $new_status, $notes);
        $history_stmt->execute();
        $history_stmt->close();
        
        $_SESSION['message'] = "success|Your response has been submitted successfully.";
        header("Location: view_complaint_detail.php?id=" . $complaint_id);
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to submit response: " . $stmt->error;
        header("Location: view_complaint_detail.php?id=" . $request_data['complaint_id']);
        exit;
    }
    
    $stmt->close();
} else {
    header("Location: track_complaints.php");
    exit;
}

$conn->close();
?>
