<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

$sql = "SELECT c.complaint_id, c.complaint, c.status, c.response, c.created_at, u.username AS student_username
        FROM complaints c
        JOIN users u ON c.student_username = u.username";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Error in prepared statement: " . $conn->error;
    exit;
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style_admins.css">
</head>
<body>
    <h2>Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
    <a href="admin_dashboard.php">Back to Dashboard</a>
    
    <div class="logg">
    <a  href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>



    <h3>All Complaints</h3>
    <table>
        <tr>
            <th>Complaint ID</th>
            <th>Student Username</th>
            <th>Complaint</th>
            <th>Status</th>
            <th>Response</th>
            <th>Created At</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['complaint_id']}</td>";
                echo "<td>{$row['student_username']}</td>";
                echo "<td>{$row['complaint']}</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['response']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "<td><a href='delete_complaints.php?id={$row['complaint_id']}'>Delete</a> | 
                          <a href='respond_complaints.php?id={$row['complaint_id']}'>Respond</a></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No complaints found.</td></tr>";
        }
        ?>
    </table>
</body>
</html>
