<?php
session_start();

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Fetch a list of unapproved teachers
$sql = "SELECT user_id, username FROM users WHERE role = 'teacher' AND approved = 0";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve Teachers</title>
    <link rel="stylesheet" href="style_admins.css">
</head>
<body>
    <h2>Approve Teachers</h2>
    <a href="admin_dashboard.php">Back to Dashboard</a>

    <table>
        <tr>
            <th>User ID</th>
            <th>Username</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['user_id'] . "</td>";
                echo "<td>" . $row['username'] . "</td>";
                echo "<td>";
                echo "<form action='process_approval.php' method='post'>";
                echo "<input type='hidden' name='user_id' value='" . $row['user_id'] . "'>";
                echo "<input type='submit' name='approve' value='Approve'>";
                echo "<input type='submit' name='reject' value='Reject'>";
                
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3'>No teachers to approve.</td></tr>";
        }
        ?>
    </table>
</body>
</html>
