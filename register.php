<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<h1>STUDENTS COMPLAINT SYSTEM</h1>
    <h2>User Registration</h2>
    <form action="process_register.php" method="post">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br>
        
        <label for="role">Role:</label><br>
        <select id="role" name="role" required>
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            
        </select><br><br>
        
        <input type="submit" value="Register">
        <p>Already have an account? <a href="index.php">Click to Login</a></p>
</body>
    </form>
</body>
</html>

