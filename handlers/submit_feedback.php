<?php
session_start();

require __DIR__ . '/../config/connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: " . base_url('index.php'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complaint_id'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $feedback_text = isset($_POST['feedback_text']) ? trim($_POST['feedback_text']) : '';
    $student_username = $_SESSION['username'];

    $check_stmt = $conn->prepare("SELECT status, student_username FROM complaints WHERE complaint_id = ?");
    $check_stmt->bind_param("i", $complaint_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $_SESSION['message'] = "error|Complaint not found.";
        header("Location: " . base_url('track_complaints.php'));
        exit;
    }

    $complaint_data = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($complaint_data['student_username'] !== $student_username) {
        $_SESSION['message'] = "error|Unauthorized: You can only provide feedback for your own complaints.";
        header("Location: " . base_url('track_complaints.php'));
        exit;
    }

    if ($complaint_data['status'] !== 'resolved') {
        $_SESSION['message'] = "error|Feedback can only be provided for resolved complaints.";
        header("Location: " . base_url('view_complaint_detail.php?id=' . $complaint_id));
        exit;
    }

    $existing_stmt = $conn->prepare("SELECT feedback_id FROM complaint_feedback WHERE complaint_id = ?");
    $existing_stmt->bind_param("i", $complaint_id);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();

    if ($existing_result->num_rows > 0) {
        $existing_stmt->close();
        $_SESSION['message'] = "error|You have already provided feedback for this complaint.";
        header("Location: " . base_url('view_complaint_detail.php?id=' . $complaint_id));
        exit;
    }
    $existing_stmt->close();

    if ($rating < 1 || $rating > 5) {
        $_SESSION['message'] = "error|Please provide a valid rating (1-5 stars).";
        header("Location: " . base_url('view_complaint_detail.php?id=' . $complaint_id));
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO complaint_feedback (complaint_id, student_username, rating, feedback_text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $complaint_id, $student_username, $rating, $feedback_text);

    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Thank you for your feedback!";
        header("Location: " . base_url('view_complaint_detail.php?id=' . $complaint_id));
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to submit feedback: " . $stmt->error;
        header("Location: " . base_url('view_complaint_detail.php?id=' . $complaint_id));
        exit;
    }

    $stmt->close();
} else {
    header("Location: " . base_url('track_complaints.php'));
    exit;
}

$conn->close();
