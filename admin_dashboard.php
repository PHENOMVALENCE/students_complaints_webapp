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

// 3. Pending Complaints
$resPending = $conn->query("SELECT COUNT(*) AS total FROM complaints WHERE status='pending'");
$pendingComplaints = ($resPending) ? $resPending->fetch_assoc()['total'] : 0;

// 4. In Progress Complaints
$resInProgress = $conn->query("SELECT COUNT(*) AS total FROM complaints WHERE status='in_progress'");
$inProgressComplaints = ($resInProgress) ? $resInProgress->fetch_assoc()['total'] : 0;

// 5. Resolved Complaints
$resResolved = $conn->query("SELECT COUNT(*) AS total FROM complaints WHERE status='resolved'");
$resolvedComplaints = ($resResolved) ? $resResolved->fetch_assoc()['total'] : 0;

// 6. Total Departments
$resDepts = $conn->query("SELECT COUNT(*) AS total FROM departments");
$totalDepartments = ($resDepts) ? $resDepts->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Complaint Management System</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .quick-actions {
            margin-top: var(--spacing-xl);
        }
        .quick-actions h3 {
            margin-bottom: var(--spacing-lg);
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }
        .action-buttons .btn {
            padding: var(--spacing-md) var(--spacing-lg);
            text-align: center;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-shield-alt"></i> Admin Panel</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="active"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="logout.php" onclick="return confirm('Logout of system?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-tachometer-alt"></i> Administrative Dashboard</h1>
            <div class="admin-profile">
                <i class="fas fa-user-shield"></i>
                <span>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            </div>
        </header>

        <section class="content-wrapper">
            <?php 
            $message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
            unset($_SESSION['message']);
            if ($message): 
                list($type, $text) = explode('|', $message); ?>
                <div class="alert alert-<?php echo $type; ?>" id="alertMessage" style="padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; display: flex; align-items: center; justify-content: space-between; animation: slideDown 0.3s ease-out; box-shadow: 0 2px 4px rgba(0,0,0,0.1); <?php echo $type === 'success' ? 'background: #d1fae5; color: #065f46; border-left: 4px solid #10b981;' : 'background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444;'; ?>">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($text); ?>
                    </span>
                    <button onclick="document.getElementById('alertMessage').style.display='none'" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0.25rem; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <script>
                    setTimeout(function() {
                        var alert = document.getElementById('alertMessage');
                        if (alert) {
                            alert.style.transition = 'opacity 0.3s';
                            alert.style.opacity = '0';
                            setTimeout(function() { alert.remove(); }, 300);
                        }
                    }, 5000);
                </script>
            <?php endif; ?>
            <h2 class="section-title"><i class="fas fa-chart-pie"></i> System Overview</h2>
            
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
                        <span class="label">Pending Complaints</span>
                        <h3 class="number"><?php echo $pendingComplaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon in-progress"><i class="fas fa-spinner"></i></div>
                    <div class="stat-info">
                        <span class="label">In Progress</span>
                        <h3 class="number"><?php echo $inProgressComplaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon resolved"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <span class="label">Resolved</span>
                        <h3 class="number"><?php echo $resolvedComplaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon users"><i class="fas fa-building"></i></div>
                    <div class="stat-info">
                        <span class="label">Departments</span>
                        <h3 class="number"><?php echo $totalDepartments; ?></h3>
                    </div>
                </div>
            </div>

            <div class="quick-actions card">
                <h3><i class="fas fa-bolt"></i> Quick Management Links</h3>
                <div class="action-buttons">
                    <a href="students_complaints.php" class="btn btn-primary"><i class="fas fa-exclamation-circle"></i> Review All Complaints</a>
                    <a href="users_management.php" class="btn btn-secondary"><i class="fas fa-users-cog"></i> Manage System Users</a>
                    <a href="manage_departments.php" class="btn btn-secondary"><i class="fas fa-building"></i> Manage Departments</a>
                    <a href="manage_categories.php" class="btn btn-secondary"><i class="fas fa-tags"></i> Manage Categories</a>
                    <a href="reports.php" class="btn btn-secondary"><i class="fas fa-chart-bar"></i> View Reports</a>
                </div>
            </div>
        </section>
    </main>
</div>

</body>
</html>