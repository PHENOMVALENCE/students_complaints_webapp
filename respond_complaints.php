<?php
session_start();


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $complaintId = $_GET['id'];

    
    $sql = "SELECT * FROM complaints WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $complaint = $result->fetch_assoc();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Respond to Complaint</title>
            <link rel="stylesheet" href="style_cmp.css">
        </head>
        <body>
            <h2>Respond to Complaint</h2>
            <form action="process_response.php" method="post">
                <input type="hidden" name="complaint_id" value="<?php echo $complaint['complaint_id']; ?>">
                <label for="student_username">Student Username:</label>
                <input type="text" id="student_username" name="student_username" value="<?php echo $complaint['student_username']; ?>" readonly><br>

                <label for="complaint">Complaint:</label>
                <textarea id="complaint" name="complaint" readonly><?php echo $complaint['complaint']; ?></textarea><br>

                <label for="response">Response:</label>
                <textarea id="response" name="response" required></textarea><br>

                <input type="submit" value="Submit Response">
            </form>
        </body>
        </html>
        <?php
    } else {
        echo "Complaint not found.";
        exit;
    }
} else {
    
    header("Location: error.php");
    exit;
}
?>
