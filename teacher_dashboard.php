<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Check teacher's approval status
$teacherId = $_SESSION['user_id'];
$checkApproval = "SELECT approved FROM users WHERE user_id = ?";
$stmtApproval = $conn->prepare($checkApproval);
$stmtApproval->bind_param("i", $teacherId);
$stmtApproval->execute();
$stmtApproval->bind_result($approvedStatus);
$stmtApproval->fetch();
$stmtApproval->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="style_admins.css">
</head>
<body>
    <?php
    if ($approvedStatus != 1) {
        echo "<div style='text-align: center; margin-top: 50px;'>";
        echo "Error: You are not approved to respond to complaints. Please wait for approval.";
        echo "<br><a href='logout.php'>Logout</a>";
        echo "</div>";
        $conn->close();
        exit;
    }
    ?>

    <h2>Welcome, Teacher <?php echo $_SESSION['username']; ?></h2>
    <div class="logg">
    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>


    <h3>Students' Complaints</h3>
    <table border='1'>
        <tr>
            <th>Complaint ID</th>
            <th>Student Username</th>
            <th>Complaint</th>
            <th>Status</th>
            <th>Response</th>
            <th>Action</th>
        </tr>
        <?php
        $sql = "SELECT * FROM complaints ORDER BY FIELD(status, 'pending', 'on_process', 'resolved', 'denied')";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['complaint_id'] . "</td>";
                echo "<td>" . $row['student_username'] . "</td>";
                echo "<td>" . $row['complaint'] . "</td>";
                echo "<td>" . $row['status'] . "</td>";
                echo "<td>" . ($row['status'] === 'resolved' ? $row['response'] : '') . "</td>";
                echo "<td>";

                // Display form only if the teacher is approved
                if ($approvedStatus == 1 && ($row['status'] === 'pending' || $row['status'] === 'on_process')) {
                    echo "<form action='teacher_dashboard.php' method='post'>";
                    echo "<input type='hidden' name='complaint_id' value='" . $row['complaint_id'] . "'>";
                    echo "<textarea name='response' rows='2' cols='30' placeholder='Write response...' required></textarea><br>";
                    echo "<input type='hidden' name='action' value='deny'>";
                    echo "<input type='submit' value='Deny'>";
                    echo "</form>";

                    echo "<form action='teacher_dashboard.php' method='post'>";
                    echo "<input type='hidden' name='complaint_id' value='" . $row['complaint_id'] . "'>";
                    echo "<textarea name='response' rows='2' cols='30' placeholder='Write response...' required></textarea><br>";
                    echo "<input type='hidden' name='action' value='resolve'>";
                    echo "<input type='submit' value='Resolve'>";
                    echo "</form>";

                    if ($row['status'] === 'pending') {
                        echo "<form action='teacher_dashboard.php' method='post'>";
                        echo "<input type='hidden' name='complaint_id' value='" . $row['complaint_id'] . "'>";
                        echo "<input type='hidden' name='action' value='on_process'>";
                        echo "<input type='submit' value='On Process'>";
                        echo "</form>";
                    }
                } else {
                    echo "Not approved to respond";
                }

                echo "</td>";
                echo "</tr>";
            }
        }
        ?>
    </table>
</body>
</html>

<?php
$conn->close();
?>
