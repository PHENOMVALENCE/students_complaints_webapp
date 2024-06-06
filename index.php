<?php 
session_start();

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin_dashboard.php");
            exit();
        case 'student':
            header("Location: student_dashboard.php");
            exit();
        case 'teacher':
            header("Location: teacher_dashboard.php");
            exit();
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'process_index.php';
}
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>STUDENTS COMPLAINT SYSTEM</h1>
    <h2>User Login</h2>
    <form action="process_index.php" method="post">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        
        <input type="submit" value="Login">
        <p>Don't have an account? <a href="register.php">Click to Register</a></p>
</body>
    </form>
</body>
</html>
