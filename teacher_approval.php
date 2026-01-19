<?php
session_start();

// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

$message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
unset($_SESSION['message']);

// Fetch unapproved teachers
$sql = "SELECT user_id, username, role, created_at FROM users WHERE role = 'teacher' AND approved = 0 ORDER BY created_at DESC";
$result = $conn->query($sql);

// Get statistics
$pending_count = $result->num_rows;
$approved_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND approved = 1")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Approvals | Admin</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-banner-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .stat-banner-card.warning {
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
        }
        
        .stat-banner-card.success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        }
        
        .stat-banner-card h3 {
            color: white;
            margin: 0 0 var(--spacing-xs) 0;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        
        .stat-banner-card .number {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
        }
        
        .teacher-card {
            background: var(--bg-white);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--warning);
            transition: var(--transition);
        }
        
        .teacher-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }
        
        .teacher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }
        
        .teacher-info h4 {
            margin: 0 0 var(--spacing-xs) 0;
            color: var(--text-primary);
        }
        
        .teacher-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .approval-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl);
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: var(--spacing-lg);
        }
        
        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }
        
        .empty-state p {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-shield-alt"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php" class="active"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-user-check"></i> Teacher Approval Requests</h1>
            <div class="admin-profile">
                <i class="fas fa-user-shield"></i>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <section class="content-wrapper">
            <?php if ($message): 
                list($type, $text) = explode('|', $message); ?>
                <div class="alert alert-<?php echo $type; ?>" id="alertMessage">
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

            <div class="stats-banner">
                <div class="stat-banner-card warning">
                    <h3><i class="fas fa-clock"></i> Pending Requests</h3>
                    <p class="number"><?php echo $pending_count; ?></p>
                </div>
                <div class="stat-banner-card success">
                    <h3><i class="fas fa-check-circle"></i> Approved Teachers</h3>
                    <p class="number"><?php echo $approved_count; ?></p>
                </div>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <div class="teacher-card">
                    <div class="teacher-header">
                        <div class="teacher-info">
                            <h4>
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($row['username']); ?>
                                <span class="role-badge teacher" style="margin-left: var(--spacing-sm);">
                                    Teacher
                                </span>
                            </h4>
                            <div class="teacher-meta">
                                <i class="fas fa-calendar"></i>
                                <span>Requested: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                                <span style="margin: 0 var(--spacing-sm);">•</span>
                                <i class="fas fa-id-badge"></i>
                                <span>ID: #<?php echo $row['user_id']; ?></span>
                            </div>
                        </div>
                        <div class="approval-actions">
                            <form action="process_approval.php" method="post" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <button type="submit" name="approve" class="btn-approve">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <form action="process_approval.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this teacher request? This action cannot be undone.');">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <button type="submit" name="reject" class="btn-reject">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>All Clear!</h3>
                    <p>No pending teacher approval requests at this time. All teacher accounts have been reviewed.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>
