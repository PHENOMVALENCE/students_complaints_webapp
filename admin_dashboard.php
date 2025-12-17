<?php
session_start();

// Security Check - Ensure only Admin can enter
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Fetch Totals for the Summary Cards
// 1. Total Users
$resUsers = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = $resUsers->fetch_assoc()['total'];

// 2. Total Complaints
$resComplaints = $conn->query("SELECT COUNT(*) AS total FROM complaints");
$totalComplaints = $resComplaints->fetch_assoc()['total'];

// 3. Pending Requests (Example for Teacher Approval)
$resPending = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='teacher' AND status='pending'");
$pendingTeachers = ($resPending) ? $resPending->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel</title>
    <link rel="stylesheet" href="style_adminadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3>AdminCMS</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="active"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <div class="nav-divider"></div>
            <a href="logout.php" onclick="return confirm('Logout of system?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Administrative Dashboard</h1>
            <div class="admin-profile">
                <span>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            </div>
        </header>

        <section class="content-wrapper">
            <h2 class="section-title">System Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <span class="label">Registered Users</span>
                        <h3 class="number"><?php echo $totalUsers; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon complaints"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-info">
                        <span class="label">Total Complaints</span>
                        <h3 class="number"><?php echo $totalComplaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <span class="label">Pending Teacher Approvals</span>
                        <h3 class="number"><?php echo $pendingTeachers; ?></h3>
                    </div>
                </div>
            </div>

            <div class="quick-actions card">
                <h3>Quick Management Links</h3>
                <div class="action-buttons">
                    <a href="students_complaints.php" class="btn btn-primary">Review All Complaints</a>
                    <a href="users_management.php" class="btn btn-secondary">Manage System Users</a>
                </div>
            </div>
        </section>
    </main>
</div>

</body>
</html>