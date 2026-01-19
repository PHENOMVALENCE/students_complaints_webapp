<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['role'];

if (!$complaint_id) {
    die("Invalid complaint ID");
}

// Fetch complaint with all related data
$stmt = $conn->prepare("SELECT c.*, u.username AS student_username, 
                        d.department_name, cat.category_name,
                        (SELECT COUNT(*) FROM complaint_history WHERE complaint_id = c.complaint_id) as history_count
                        FROM complaints c
                        JOIN users u ON c.student_username = u.username
                        LEFT JOIN departments d ON c.department_id = d.department_id
                        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                        WHERE c.complaint_id = ?");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Complaint not found");
}

$complaint = $result->fetch_assoc();
$stmt->close();

// Security check: Students can only view their own complaints
if ($role === 'student' && $complaint['student_username'] !== $_SESSION['username']) {
    die("Unauthorized: You can only view your own complaints.");
}

// Security check: Department officers can only view complaints from their department
if ($role === 'department_officer') {
    $user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
    $user_stmt->bind_param("s", $_SESSION['username']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data || $user_data['department_id'] != $complaint['department_id']) {
        die("Unauthorized: This complaint does not belong to your department.");
    }
}

// Fetch complaint history
$history_stmt = $conn->prepare("SELECT * FROM complaint_history WHERE complaint_id = ? ORDER BY created_at ASC");
$history_stmt->bind_param("i", $complaint_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details #<?php echo $complaint_id; ?> - CMS</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .detail-card {
            background: var(--bg-white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-lg);
        }
        .detail-header {
            border-bottom: 2px solid var(--border);
            padding-bottom: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .detail-header h2 {
            margin: 0;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .detail-value {
            color: var(--text-primary);
        }
        .history-item {
            padding: var(--spacing-md);
            border-left: 4px solid var(--primary);
            margin-bottom: var(--spacing-md);
            background: var(--bg-light);
            border-radius: var(--radius);
        }
        .history-item .history-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-sm);
        }
        .history-item .history-action {
            font-weight: 600;
            color: var(--primary);
        }
        .history-item .history-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: var(--radius);
            transition: var(--transition);
        }
        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateX(-4px);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-file-alt"></i> Complaint Details</h3>
        </div>
        <nav class="sidebar-nav">
            <?php if ($role === 'student'): ?>
                <a href="student_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <?php elseif ($role === 'teacher'): ?>
                <a href="teacher_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <?php elseif ($role === 'admin'): ?>
                <a href="students_complaints.php"><i class="fas fa-arrow-left"></i> Back to Complaints</a>
            <?php else: ?>
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <?php endif; ?>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>
    <main class="main-content">
        <section class="content-wrapper">
            <div class="detail-container">

                <div class="detail-card">
                    <div class="detail-header">
                        <h2><i class="fas fa-file-invoice"></i> Complaint #<?php echo $complaint['complaint_id']; ?></h2>
                        <span class="status-badge <?php echo strtolower(str_replace('_', '-', $complaint['status'])); ?>">
                            <i class="fas <?php 
                                if ($complaint['status'] === 'pending') echo 'fa-clock';
                                elseif ($complaint['status'] === 'in_progress') echo 'fa-spinner';
                                elseif ($complaint['status'] === 'resolved') echo 'fa-check-circle';
                                else echo 'fa-times-circle';
                            ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Title:</div>
                        <div class="detail-value"><strong><?php echo htmlspecialchars($complaint['title'] ?? 'No title'); ?></strong></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Student:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['student_username']); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Department:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['department_name'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['category_name'] ?? 'Uncategorized'); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Submitted:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($complaint['created_at'])); ?></div>
                    </div>

                    <?php if ($complaint['routed_at']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Routed:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($complaint['routed_at'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($complaint['updated_at']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Last Updated:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($complaint['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row" style="grid-template-columns: 1fr;">
                        <div>
                            <div class="detail-label">Description:</div>
                            <div class="detail-value" style="margin-top: 0.5rem; white-space: pre-wrap;"><?php echo htmlspecialchars($complaint['complaint']); ?></div>
                        </div>
                    </div>

                    <?php if ($complaint['response']): ?>
                    <div class="detail-row" style="grid-template-columns: 1fr;">
                        <div>
                            <div class="detail-label"><i class="fas fa-reply"></i> Response/Resolution:</div>
                            <div class="detail-value" style="margin-top: var(--spacing-sm); white-space: pre-wrap; background: var(--info-light); padding: var(--spacing-md); border-radius: var(--radius); border-left: 4px solid var(--info);"><?php echo htmlspecialchars($complaint['response']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($history): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-history"></i> Complaint Timeline</h3>
                    <?php foreach ($history as $item): ?>
                    <div class="history-item">
                        <div class="history-header">
                            <span class="history-action"><?php echo ucfirst(str_replace('_', ' ', $item['action'])); ?></span>
                            <span class="history-date"><?php echo date('M d, Y g:i A', strtotime($item['created_at'])); ?></span>
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">
                            <strong>Performed by:</strong> <?php echo htmlspecialchars($item['performed_by']); ?><br>
                            <?php if ($item['old_status'] && $item['new_status']): ?>
                                <strong>Status:</strong> <?php echo ucfirst($item['old_status']); ?> → <?php echo ucfirst(str_replace('_', ' ', $item['new_status'])); ?><br>
                            <?php endif; ?>
                            <?php if ($item['notes']): ?>
                                <strong>Notes:</strong> <?php echo htmlspecialchars($item['notes']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

</body>
</html>

