<?php
session_start();

require __DIR__ . '/../config/connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $row['role'];
            $role = $row['role'];
            
            switch ($role) {
                case 'student':
                    header("Location: " . base_url('student_dashboard.php'));
                    break;
                case 'teacher':
                    header("Location: " . base_url('teacher_dashboard.php'));
                    break;
                case 'admin':
                    header("Location: " . base_url('admin_dashboard.php'));
                    break;
                case 'department_officer':
                    header("Location: " . base_url('department_officer_dashboard.php'));
                    break;
                default:
                    header("Location: " . base_url('default_dashboard.php'));
                    break;
            }
        } else {
            echo "Invalid password";
        }
    } else {
        echo "User not found";
    }
}

$conn->close();
