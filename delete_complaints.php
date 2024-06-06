<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $complaintId = $_GET['id'];

   
    $sql = "DELETE FROM complaints WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();

    header("Location: admin_dashboard.php");
    exit;
} else {

    header("Location: error.php");
    exit;
}
?>
