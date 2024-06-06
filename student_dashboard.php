<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $complaint = $_POST['complaint'];
    $student_username = $_SESSION['username'];

    $sql = "INSERT INTO complaints (student_username, complaint, status) VALUES ('$student_username', '$complaint', 'pending')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Complaint submitted successfully!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $complaint_id = $_GET['delete'];
    $student_username = $_SESSION['username'];

    $sql = "DELETE FROM complaints WHERE complaint_id='$complaint_id' AND student_username='$student_username'";
    
    if ($conn->query($sql) === TRUE) {
        echo "Complaint deleted successfully!";
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}

$student_username = $_SESSION['username'];
$sql = "SELECT * FROM complaints WHERE student_username='$student_username'";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style_dassh.css">
</head>
<body>
<h1>STUDENTS COMPLAINT SYSTEM</h1>
    <h2>Welcome, Student <?php echo $_SESSION['username']; ?></h2>
    <a href="logout.php">Logout</a> 
    <h3>Submit Complaint</h3>
    <form action="student_dashboard.php" method="post">
        <textarea name="complaint" rows="4" cols="50" required></textarea><br><br>
        <input type="submit" value="Submit Complaint">
    </form>

    <h3>My Complaints</h3>
    <table>
        <tr>
            <th>Complaint ID</th>
            <th>Complaint</th>
            <th>Status</th>
            <th>Teacher's Response</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result === false) {
            echo "<tr><td colspan='5'>Error: " . $conn->error . "</td></tr>";
        } elseif ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['complaint_id'] . "</td>";
                echo "<td>" . $row['complaint'] . "</td>";
                echo "<td>" . $row['status'] . "</td>";
                echo "<td>" . $row['response'] . "</td>";
                echo "<td><a href='student_dashboard.php?delete=" . $row['complaint_id'] . "'>Delete</a></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No complaints found.</td></tr>";
        }
        ?>
    </table>
</body>
</html>
