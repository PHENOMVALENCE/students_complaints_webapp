<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

// Query to get the number of registered users
$sqlRegisteredUsers = "SELECT COUNT(*) AS total_users FROM users";
$resultRegisteredUsers = $conn->query($sqlRegisteredUsers);
$rowRegisteredUsers = $resultRegisteredUsers->fetch_assoc();
$totalUsers = $rowRegisteredUsers['total_users'];

// Query to get the number of filed complaints
$sqlFiledComplaints = "SELECT COUNT(*) AS total_complaints FROM complaints";
$resultFiledComplaints = $conn->query($sqlFiledComplaints);
$rowFiledComplaints = $resultFiledComplaints->fetch_assoc();
$totalComplaints = $rowFiledComplaints['total_complaints'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style_adminadmin.css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="teacher_approval.php">Teacher Requests</a></li>
            <li><a href="students_complaints.php">Students Complaints</a></li>
            <li><a href="users_management.php">Users Management</a></li>
            
        </ul>
    </nav>
    <h2>Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
    <div class="logg">
    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>


    <div class="dashboard-summary">
        <table>
            <tr>
                <th>Registered Users</th>
                <th>Filed Complaints</th>
            </tr>
            <tr>
                <td><?php echo $totalUsers; ?></td>
                <td><?php echo $totalComplaints; ?></td>
            </tr>
        </table>
    </div>



  
</body>
</html>
