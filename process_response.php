<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $complaintId = $_POST['complaint_id'];
    $response = $_POST['response'];

    $sql = "UPDATE complaints SET response = ?, status = 'resolved' WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $response, $complaintId);

    if ($stmt->execute()) {
        echo "Response submitted successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    header("Location: error.php");
    exit;
}

$conn->close();
?>
