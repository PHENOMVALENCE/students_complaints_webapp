<?php
session_start();

// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'connect.php';
$message = "";

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $comp_id = $_GET['delete'];
    $user = $_SESSION['username'];

    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ? AND student_username = ? AND status = 'pending'");
    $stmt->bind_param("is", $comp_id, $user);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Complaint removed successfully.";
        header("Location: track_complaints.php");
        exit;
    } else {
        $message = "error|Failed to delete complaint.";
    }
    $stmt->close();
}

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$student_username = $_SESSION['username'];
$where_clause = "WHERE c.student_username = ?";
$params = [$student_username];
$param_types = "s";

if ($filter !== 'all' && in_array($filter, ['pending', 'in_progress', 'resolved', 'denied', 'awaiting_student_response'])) {
    $where_clause .= " AND c.status = ?";
    $params[] = $filter;
    $param_types .= "s";
}

$sql = "SELECT c.*, d.department_name, cat.category_name,
        (SELECT COUNT(*) FROM information_requests WHERE complaint_id = c.complaint_id AND status = 'pending') as pending_requests
        FROM complaints c 
        LEFT JOIN departments d ON c.department_id = d.department_id 
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id 
        $where_clause
        ORDER BY FIELD(c.status, 'awaiting_student_response', 'pending', 'in_progress', 'resolved', 'denied'), c.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$complaints = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get counts for filter tabs (include awaiting_student_response)
$counts = [];
$statuses = ['all', 'awaiting_student_response', 'pending', 'in_progress', 'resolved', 'denied'];
foreach ($statuses as $status) {
    if ($status === 'all') {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE student_username = ?");
        $count_stmt->bind_param("s", $student_username);
    } else {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE student_username = ? AND status = ?");
        $count_stmt->bind_param("ss", $student_username, $status);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_data = $count_result->fetch_assoc();
    $counts[$status] = $count_data['count'];
    $count_stmt->close();
}

// Pending info requests count (for "action needed" highlight)
$action_needed_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.complaint_id) as cnt FROM complaints c JOIN information_requests ir ON ir.complaint_id = c.complaint_id WHERE c.student_username = ? AND ir.status = 'pending'");
$action_needed_stmt->bind_param("s", $student_username);
$action_needed_stmt->execute();
$action_needed_result = $action_needed_stmt->get_result();
$action_needed_count = $action_needed_result->fetch_assoc()['cnt'] ?? 0;
$action_needed_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Complaints - Student Portal</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
            border-bottom: 2px solid var(--border);
            padding-bottom: var(--spacing-md);
        }
        
        .filter-tab {
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            border-radius: var(--radius) var(--radius) 0 0;
            transition: var(--transition);
            position: relative;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .filter-tab:hover {
            color: var(--primary);
            background: var(--bg-light);
        }
        
        .filter-tab.active {
            color: var(--primary);
            background: var(--bg-light);
        }
        
        .filter-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .filter-tab .badge {
            background: var(--border);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .filter-tab.active .badge {
            background: var(--primary);
            color: white;
        }
        
        .complaint-card {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .complaint-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }
        
        .complaint-card.pending {
            border-left-color: var(--warning);
        }
        
        .complaint-card.awaiting-student-response {
            border-left-color: var(--warning);
            border-left-width: 6px;
        }
        
        .complaint-card.in-progress {
            border-left-color: var(--info);
        }
        
        .complaint-card.resolved {
            border-left-color: var(--success);
        }
        
        .complaint-card.denied {
            border-left-color: var(--danger);
        }
        
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }
        
        .complaint-title {
            flex: 1;
        }
        
        .complaint-title h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
            font-size: 1.25rem;
        }
        
        .complaint-title .complaint-id {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .complaint-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-lg);
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .meta-item i {
            color: var(--primary);
        }
        
        .complaint-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin: var(--spacing-md) 0;
        }
        
        .complaint-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
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
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
        }
        
        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xl);
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
            <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="create_complaint.php"><i class="fas fa-plus-circle"></i> Submit Complaint</a>
            <a href="track_complaints.php" class="active"><i class="fas fa-list-alt"></i> Track Complaints</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-list-alt"></i> Track My Complaints</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
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

            <?php if ($action_needed_count > 0): ?>
            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 6px solid var(--warning); padding: var(--spacing-lg); border-radius: var(--radius-lg); margin-bottom: var(--spacing-xl);">
                <strong style="color: #92400e;"><i class="fas fa-exclamation-circle"></i> Action required</strong>
                <p style="margin: var(--spacing-xs) 0 0 0; color: #78350f;">You have <?php echo $action_needed_count; ?> complaint(s) with a request for more information from staff. Open them and submit your response.</p>
            </div>
            <?php endif; ?>

            <div class="filter-tabs">
                <a href="track_complaints.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All
                    <span class="badge"><?php echo $counts['all']; ?></span>
                </a>
                <a href="track_complaints.php?filter=awaiting_student_response" class="filter-tab <?php echo $filter === 'awaiting_student_response' ? 'active' : ''; ?>" style="<?php echo $action_needed_count > 0 ? 'border-bottom: 2px solid var(--warning);' : ''; ?>">
                    <i class="fas fa-user-check"></i> Awaiting your response
                    <span class="badge"><?php echo $counts['awaiting_student_response']; ?></span>
                </a>
                <a href="track_complaints.php?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                    <span class="badge"><?php echo $counts['pending']; ?></span>
                </a>
                <a href="track_complaints.php?filter=in_progress" class="filter-tab <?php echo $filter === 'in_progress' ? 'active' : ''; ?>">
                    <i class="fas fa-spinner"></i> In Progress
                    <span class="badge"><?php echo $counts['in_progress']; ?></span>
                </a>
                <a href="track_complaints.php?filter=resolved" class="filter-tab <?php echo $filter === 'resolved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Resolved
                    <span class="badge"><?php echo $counts['resolved']; ?></span>
                </a>
                <a href="track_complaints.php?filter=denied" class="filter-tab <?php echo $filter === 'denied' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Denied
                    <span class="badge"><?php echo $counts['denied']; ?></span>
                </a>
            </div>

            <?php if (!empty($complaints)): ?>
                <?php foreach ($complaints as $row): ?>
                <div class="complaint-card <?php echo strtolower(str_replace('_', '-', $row['status'])); ?>">
                    <div class="complaint-header">
                        <div class="complaint-title">
                            <span class="complaint-id">Complaint #<?php echo $row['complaint_id']; ?></span>
                            <h3><?php echo htmlspecialchars($row['title'] ?? 'No title'); ?></h3>
                        </div>
                        <span class="badge <?php echo strtolower(str_replace('_', '-', $row['status'])); ?>">
                            <i class="fas <?php 
                                if ($row['status'] === 'pending') echo 'fa-clock';
                                elseif ($row['status'] === 'in_progress') echo 'fa-spinner';
                                elseif ($row['status'] === 'awaiting_student_response') echo 'fa-user-check';
                                elseif ($row['status'] === 'resolved') echo 'fa-check-circle';
                                else echo 'fa-times-circle';
                            ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                        </span>
                        <?php if (($row['pending_requests'] ?? 0) > 0): ?>
                            <span style="margin-left: var(--spacing-sm); padding: 2px 8px; background: var(--warning); color: #78350f; border-radius: var(--radius); font-size: 0.75rem; font-weight: 600;">
                                <i class="fas fa-exclamation-circle"></i> Response needed
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="complaint-description">
                        <?php echo nl2br(htmlspecialchars(substr($row['complaint'], 0, 200))); ?>
                        <?php if (strlen($row['complaint']) > 200): ?>...<?php endif; ?>
                    </div>
                    
                    <div class="complaint-meta">
                        <div class="meta-item">
                            <i class="fas fa-building"></i>
                            <span><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Submitted: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                        </div>
                        <?php if ($row['updated_at'] && $row['updated_at'] != $row['created_at']): ?>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span>Updated: <?php echo date('M d, Y', strtotime($row['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="complaint-actions">
                        <?php if (($row['pending_requests'] ?? 0) > 0): ?>
                        <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>#info-requests" class="btn btn-primary" style="background: var(--warning); border-color: var(--warning);">
                            <i class="fas fa-reply"></i> Respond to Request
                        </a>
                        <?php endif; ?>
                        <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($row['status'] === 'pending' && empty($row['pending_requests'])): ?>
                        <a href="?delete=<?php echo $row['complaint_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this complaint? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Complaints Found</h3>
                    <p>
                        <?php if ($filter === 'all'): ?>
                            You haven't submitted any complaints yet. Start by creating your first complaint!
                        <?php else: ?>
                            You don't have any <?php echo $filter === 'awaiting_student_response' ? 'complaints awaiting your response' : ucfirst(str_replace('_', ' ', $filter)); ?> complaints at the moment.
                        <?php endif; ?>
                    </p>
                    <?php if ($filter === 'all'): ?>
                    <a href="create_complaint.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Submit Your First Complaint
                    </a>
                    <?php else: ?>
                    <a href="track_complaints.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> View All Complaints
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>
