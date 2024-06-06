<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <link rel="stylesheet" href="style_users.css">
</head>
<body>

<h1>Users Management</h1>
<div class="dashh">
<a href="admin_dashboard.php">Back to Dashboard</a>
</div>

<?php
include 'connect.php'; // Include the database connection file

// Function to get the list of users from the database
function getUsersList() {
    global $conn; // Access the global $conn variable

    $sql = "SELECT user_id, username FROM users";
    $result = $conn->query($sql);

    $users = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    return $users;
}

// Display users in a table
$users = getUsersList();

if (empty($users)) {
    echo "<p>No users found.</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Actions</th></tr>";

    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td><form method='post' action='delete_user.php'>";
        echo "<input type='hidden' name='user_id' value='{$user['user_id']}'>";
        echo "<button type='submit'>Delete</button>";
        echo "</form></td>";
        echo "</tr>";
    }

    echo "</table>";
}

$conn->close(); // Close the connection at the end of the script

?>

</body>
</html>
