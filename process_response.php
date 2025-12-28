<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $complaintId = (int)$_POST['complaint_id'];
    $response = trim($_POST['response']);

    // Get old status for history
    $old_status_stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $old_status_stmt->bind_param("i", $complaintId);
    $old_status_stmt->execute();
    $old_status_result = $old_status_stmt->get_result();
    $old_status_data = $old_status_result->fetch_assoc();
    $old_status = $old_status_data['status'];
    $old_status_stmt->close();

    $sql = "UPDATE complaints SET response = ?, status = 'resolved', updated_at = NOW() WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $response, $complaintId);

    if ($stmt->execute()) {
        // Log in history
        $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, 'resolved', ?, ?, 'resolved', 'Complaint resolved by administrator')");
        $history_stmt->bind_param("iss", $complaintId, $_SESSION['username'], $old_status);
        $history_stmt->execute();
        $history_stmt->close();
        
        $_SESSION['message'] = "success|Response submitted successfully!";
        header("Location: students_complaints.php");
    } else {
        $_SESSION['message'] = "error|Failed to submit response: " . $stmt->error;
        header("Location: respond_complaints.php?id=" . $complaintId);
    }

    $stmt->close();
} else {
    header("Location: students_complaints.php");
    exit;
}

$conn->close();
?>
