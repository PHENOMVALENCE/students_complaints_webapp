<?php
include 'connect.php'; // Include the database connection file

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['user_id'])) {
    // Sanitize user input
    $userId = intval($_GET['user_id']);

    // Prepare and execute the SQL statement
    $sqlDeleteUser = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sqlDeleteUser);
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        // User deleted successfully, redirect to users_management.php
        header("Location: users_management.php");
        exit;
    } else {
        echo "Error deleting user: " . $stmt->error;
    }

    // Close the prepared statement
    $stmt->close();
}

// Close the database connection
$conn->close();
?>
