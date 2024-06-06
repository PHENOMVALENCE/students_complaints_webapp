<?php
include 'connect.php';

if (isset($_GET['user_id'])) {
    $userId = $_GET['user_id'];

    // Get the username and complaints for the user using a JOIN
    $sql = "SELECT u.username, c.complaint_id, c.complaint
            FROM users u
            JOIN complaints c ON u.user_id = c.user_id
            WHERE u.user_id = $userId";

    $result = $conn->query($sql);

    if (!$result) {
        die("Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        echo "<h2>Complaints for user: " . $result->fetch_assoc()['username'] . "</h2>";

        echo "<table border='1'>";
        echo "<tr><th>Complaint ID</th><th>Complaint Text</th></tr>";

        while ($rowComplaint = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$rowComplaint['complaint_id']}</td>";
            echo "<td>{$rowComplaint['complaint']}</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p>No complaints found for this user.</p>";
    }

    // Display the complaints count
    $complaintsCount = $result->num_rows;
    echo "<p>Number of complaints: $complaintsCount</p>";
} else {
    echo "<p>User ID not specified.</p>";
}

$conn->close();
?>
