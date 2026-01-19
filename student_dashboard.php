<?php
session_start();

// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Get message from session
$message = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fetch statistics for the student
$student_username = $_SESSION['username'];

// Total complaints
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_username = ?");
$total_stmt->bind_param("s", $student_username);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_data = $total_result->fetch_assoc();
$total_complaints = $total_data['total'];
$total_stmt->close();

// Pending complaints
$pending_stmt = $conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_username = ? AND status = 'pending'");
$pending_stmt->bind_param("s", $student_username);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_data = $pending_result->fetch_assoc();
$pending_complaints = $pending_data['total'];
$pending_stmt->close();

// In Progress complaints
$inprogress_stmt = $conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_username = ? AND status = 'in_progress'");
$inprogress_stmt->bind_param("s", $student_username);
$inprogress_stmt->execute();
$inprogress_result = $inprogress_stmt->get_result();
$inprogress_data = $inprogress_result->fetch_assoc();
$inprogress_complaints = $inprogress_data['total'];
$inprogress_stmt->close();

// Resolved complaints
$resolved_stmt = $conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_username = ? AND status = 'resolved'");
$resolved_stmt->bind_param("s", $student_username);
$resolved_stmt->execute();
$resolved_result = $resolved_stmt->get_result();
$resolved_data = $resolved_result->fetch_assoc();
$resolved_complaints = $resolved_data['total'];
$resolved_stmt->close();

// Recent complaints (last 5)
$recent_stmt = $conn->prepare("SELECT c.*, d.department_name, cat.category_name 
                              FROM complaints c 
                              LEFT JOIN departments d ON c.department_id = d.department_id 
                              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id 
                              WHERE c.student_username = ? 
                              ORDER BY c.created_at DESC 
                              LIMIT 5");
$recent_stmt->bind_param("s", $student_username);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_complaints = $recent_result->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Complaint Management System</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-banner h2 {
            color: white;
            margin-bottom: var(--spacing-sm);
            font-size: 2rem;
        }
        
        .welcome-banner p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            margin: 0;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .quick-action-card {
            background: var(--bg-white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .quick-action-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: var(--spacing-md);
        }
        
        .quick-action-card h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .quick-action-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .recent-complaints {
            margin-top: var(--spacing-xl);
        }
        
        .complaint-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        
        .complaint-item:hover {
            background: var(--bg-light);
        }
        
        .complaint-item:last-child {
            border-bottom: none;
        }
        
        .complaint-info {
            flex: 1;
        }
        
        .complaint-info strong {
            display: block;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .complaint-info small {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .complaint-status {
            margin-left: var(--spacing-md);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-graduation-cap"></i> Student Portal</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="create_complaint.php"><i class="fas fa-plus-circle"></i> Submit Complaint</a>
            <a href="track_complaints.php"><i class="fas fa-list-alt"></i> Track Complaints</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-tachometer-alt"></i> Student Dashboard</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
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

            <div class="welcome-banner">
                <h2><i class="fas fa-hand-sparkles"></i> Welcome Back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p>Manage your complaints and track their status from your personalized dashboard.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-info">
                        <span class="label">Total Complaints</span>
                        <h3><?php echo $total_complaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <span class="label">Pending</span>
                        <h3><?php echo $pending_complaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon in-progress"><i class="fas fa-spinner"></i></div>
                    <div class="stat-info">
                        <span class="label">In Progress</span>
                        <h3><?php echo $inprogress_complaints; ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon resolved"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <span class="label">Resolved</span>
                        <h3><?php echo $resolved_complaints; ?></h3>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="create_complaint.php" class="quick-action-card">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Submit New Complaint</h3>
                    <p>File a new complaint and get it resolved quickly</p>
                </a>

                <a href="track_complaints.php" class="quick-action-card">
                    <i class="fas fa-list-alt"></i>
                    <h3>Track Complaints</h3>
                    <p>View and monitor all your submitted complaints</p>
                </a>

                <a href="track_complaints.php?filter=pending" class="quick-action-card">
                    <i class="fas fa-clock"></i>
                    <h3>Pending Issues</h3>
                    <p>Check complaints awaiting response</p>
                </a>
            </div>

            <?php if (!empty($recent_complaints)): ?>
            <div class="card recent-complaints">
                <h3><i class="fas fa-history"></i> Recent Complaints</h3>
                <div>
                    <?php foreach ($recent_complaints as $complaint): ?>
                    <div class="complaint-item">
                        <div class="complaint-info">
                            <strong>#<?php echo $complaint['complaint_id']; ?> - <?php echo htmlspecialchars($complaint['title'] ?? 'No title'); ?></strong>
                            <small>
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($complaint['department_name'] ?? 'N/A'); ?> 
                                • <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?>
                            </small>
                        </div>
                        <div class="complaint-status">
                            <span class="badge <?php echo strtolower(str_replace('_', '-', $complaint['status'])); ?>">
                                <i class="fas <?php 
                                    if ($complaint['status'] === 'pending') echo 'fa-clock';
                                    elseif ($complaint['status'] === 'in_progress') echo 'fa-spinner';
                                    elseif ($complaint['status'] === 'resolved') echo 'fa-check-circle';
                                    else echo 'fa-times-circle';
                                ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 1px solid var(--border);">
                    <a href="track_complaints.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> View All Complaints
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="card" style="text-align: center; padding: var(--spacing-2xl);">
                <i class="fas fa-inbox" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></i>
                <h3 style="color: var(--text-primary); margin-bottom: var(--spacing-md);">No Complaints Yet</h3>
                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-xl);">Start by submitting your first complaint to get help with your concerns.</p>
                <a href="create_complaint.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: var(--spacing-sm);">
                    <i class="fas fa-plus-circle"></i> Submit Your First Complaint
                </a>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>
