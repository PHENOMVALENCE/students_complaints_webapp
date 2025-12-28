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
    <title>Complaint Details #<?php echo $complaint_id; ?></title>
    <link rel="stylesheet" href="style_dassh.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .detail-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .detail-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.in-progress { background: #dbeafe; color: #1e40af; }
        .status-badge.resolved { background: #d1fae5; color: #065f46; }
        .history-item {
            padding: 1rem;
            border-left: 3px solid #667eea;
            margin-bottom: 1rem;
            background: #f9fafb;
        }
        .history-item .history-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .history-item .history-action {
            font-weight: 600;
            color: #667eea;
        }
        .history-item .history-date {
            color: #666;
            font-size: 0.85rem;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 1.5rem;
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <main class="main-content" style="margin-left: 0;">
        <section class="content-wrapper">
            <div class="detail-container">
                <a href="<?php 
                    if ($role === 'student') echo 'student_dashboard.php';
                    elseif ($role === 'department_officer') echo 'department_officer_dashboard.php';
                    else echo 'students_complaints.php';
                ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>

                <div class="detail-card">
                    <div class="detail-header">
                        <h2>Complaint #<?php echo $complaint['complaint_id']; ?></h2>
                        <span class="status-badge <?php echo strtolower(str_replace('_', '-', $complaint['status'])); ?>">
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
                            <div class="detail-label">Response/Resolution:</div>
                            <div class="detail-value" style="margin-top: 0.5rem; white-space: pre-wrap; background: #f0f9ff; padding: 1rem; border-radius: 4px;"><?php echo htmlspecialchars($complaint['response']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($history): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-history"></i> Complaint History</h3>
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

