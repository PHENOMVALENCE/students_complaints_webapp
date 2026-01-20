<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Check teacher's approval status
$teacherId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$teacherId) {
    $user_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $user_stmt->bind_param("s", $_SESSION['username']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $teacherId = $user_data['user_id'];
    $user_stmt->close();
}

$checkApproval = "SELECT approved FROM users WHERE user_id = ?";
$stmtApproval = $conn->prepare($checkApproval);
$stmtApproval->bind_param("i", $teacherId);
$stmtApproval->execute();
$stmtApproval->bind_result($approvedStatus);
$stmtApproval->fetch();
$stmtApproval->close();

$message = "";

// Handle complaint actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $approvedStatus == 1) {
    $complaint_id = (int)$_POST['complaint_id'];
    $action = $_POST['action'];
    $response = trim($_POST['response'] ?? '');
    
    // Check if complaint exists
    $check_stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $check_stmt->bind_param("i", $complaint_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $message = "error|Complaint not found.";
    } else {
        $old_status_data = $check_result->fetch_assoc();
        $old_status = $old_status_data['status'] ?? 'pending';
        $check_stmt->close();
        
        $new_status = '';
        switch ($action) {
            case 'in_progress':
                $new_status = 'in_progress';
                break;
            case 'resolve':
                if (empty($response)) {
                    $message = "error|Please provide a response when resolving.";
                } else {
                    $new_status = 'resolved';
                }
                break;
            case 'deny':
                $new_status = 'denied';
                // For deny, response is optional but recommended
                if (empty($response)) {
                    $response = 'Complaint denied by teacher.';
                }
                break;
        }
        
        if ($new_status && empty($message)) {
            $update_stmt = $conn->prepare("UPDATE complaints SET status = ?, response = ?, updated_at = NOW() WHERE complaint_id = ?");
            $update_stmt->bind_param("ssi", $new_status, $response, $complaint_id);
            
            if ($update_stmt->execute()) {
                // Log in history
                $history_notes = "Status updated by teacher";
                if ($new_status === 'resolved') {
                    $history_notes = "Complaint resolved by teacher";
                } elseif ($new_status === 'denied') {
                    $history_notes = "Complaint denied by teacher";
                }
                
                $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $history_stmt->bind_param("isssss", $complaint_id, $action, $_SESSION['username'], $old_status, $new_status, $history_notes);
                $history_stmt->execute();
                $history_stmt->close();
                
                $_SESSION['message'] = "success|Complaint status updated successfully!";
                header("Location: teacher_dashboard.php");
                exit;
            } else {
                $message = "error|Update failed: " . $conn->error;
            }
            $update_stmt->close();
        }
    }
}

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fetch complaints with joins
$sql = "SELECT c.*, d.department_name, cat.category_name 
        FROM complaints c
        LEFT JOIN departments d ON c.department_id = d.department_id
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        ORDER BY FIELD(c.status, 'pending', 'in_progress', 'resolved', 'denied'), c.created_at DESC";
$result = $conn->query($sql);
$complaints = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Complaint Management System</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .approval-warning {
            background: linear-gradient(135deg, var(--warning-light) 0%, #fde68a 100%);
            border-left: 4px solid var(--warning);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            text-align: center;
            margin: var(--spacing-xl) 0;
            box-shadow: var(--shadow);
        }
        .approval-warning h2 {
            color: #92400e;
            margin: 0 0 var(--spacing-md) 0;
        }
        .approval-warning p {
            color: #78350f;
            margin: 0 0 var(--spacing-lg) 0;
        }
        .response-textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            resize: vertical;
            margin-bottom: var(--spacing-sm);
            transition: var(--transition);
        }
        .response-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Portal</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="teacher_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="teacher_dashboard.php#complaints"><i class="fas fa-list-alt"></i> All Complaints</a>
            <div class="nav-divider"></div>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-tachometer-alt"></i> Teacher Dashboard</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            </div>
        </header>

        <section class="content-wrapper">
            <?php if ($approvedStatus != 1): ?>
                <div class="approval-warning">
                    <h2><i class="fas fa-exclamation-triangle"></i> Approval Pending</h2>
                    <p>You are not yet approved to respond to complaints. Please wait for administrator approval.</p>
                    <a href="logout.php" class="btn-submit" style="display: inline-block; text-decoration: none; padding: 0.75rem 1.5rem;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <?php if ($message): 
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

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon total"><i class="fas fa-file-invoice"></i></div>
                        <div class="stat-info">
                            <span class="label">Total Complaints</span>
                            <h3><?php echo $stats['total']; ?></h3>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                        <div class="stat-info">
                            <span class="label">Pending</span>
                            <h3><?php echo $stats['pending']; ?></h3>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon in-progress"><i class="fas fa-spinner"></i></div>
                        <div class="stat-info">
                            <span class="label">In Progress</span>
                            <h3><?php echo $stats['in_progress']; ?></h3>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon resolved"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info">
                            <span class="label">Resolved</span>
                            <h3><?php echo $stats['resolved']; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card table-card" id="complaints">
                    <h3><i class="fas fa-list-alt"></i> All Student Complaints</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Department</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($complaints): foreach ($complaints as $row): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['complaint_id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['title'] ?? 'No title'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($row['complaint'], 0, 60)) . (strlen($row['complaint']) > 60 ? '...' : ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['student_username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower(str_replace('_', '-', $row['status'])); ?>">
                                            <i class="fas <?php 
                                                if ($row['status'] === 'pending') echo 'fa-clock';
                                                elseif ($row['status'] === 'in_progress') echo 'fa-spinner';
                                                elseif ($row['status'] === 'resolved') echo 'fa-check-circle';
                                                else echo 'fa-times-circle';
                                            ?>"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn-view" title="View Details" style="color: #667eea; text-decoration: none; padding: 0.5rem; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='transparent'">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($row['status'] === 'pending' || $row['status'] === 'in_progress'): ?>
                                            <button onclick="showActionForm(<?php echo $row['complaint_id']; ?>, '<?php echo $row['status']; ?>')" class="btn-view" title="Update Status" style="color: #667eea; text-decoration: none; padding: 0.5rem; border-radius: 4px; transition: background 0.2s; background: none; border: none; cursor: pointer;" onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='transparent'">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="8" class="empty">No complaints found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Action Modal -->
<div id="actionModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-top: 0;">Update Complaint Status</h3>
        <form method="post" id="actionForm">
            <input type="hidden" name="complaint_id" id="modal_complaint_id">
            <input type="hidden" name="action" id="modal_action">
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">New Status</label>
                <div class="action-buttons">
                    <button type="button" onclick="setAction('in_progress')" class="btn-action btn-in-progress">
                        <i class="fas fa-spinner"></i> Mark In Progress
                    </button>
                    <button type="button" onclick="setAction('resolve')" class="btn-action btn-resolve">
                        <i class="fas fa-check"></i> Resolve
                    </button>
                    <button type="button" onclick="setAction('deny')" class="btn-action btn-deny">
                        <i class="fas fa-times"></i> Deny
                    </button>
                </div>
            </div>
            
            <div id="responseGroup" style="display:none; margin-bottom: 1rem;">
                <label for="response" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Response/Resolution Details <span style="color: red;">*</span></label>
                <textarea id="response" name="response" class="response-textarea" rows="4" placeholder="Provide details about the resolution or denial..."></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn-submit" style="flex: 1;">
                    <i class="fas fa-save"></i> Update
                </button>
                <button type="button" onclick="closeModal()" class="btn" style="padding: 0.75rem 1.5rem; background: #e2e8f0; color: #4a5568; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showActionForm(complaintId, currentStatus) {
    document.getElementById('modal_complaint_id').value = complaintId;
    document.getElementById('actionModal').classList.add('active');
}

function setAction(action) {
    document.getElementById('modal_action').value = action;
    var responseGroup = document.getElementById('responseGroup');
    var responseField = document.getElementById('response');
    
    if (action === 'resolve') {
        responseGroup.style.display = 'block';
        responseField.required = true;
        responseField.placeholder = 'Provide details about the resolution...';
    } else if (action === 'deny') {
        responseGroup.style.display = 'block';
        responseField.required = false; // Optional for deny
        responseField.placeholder = 'Provide reason for denial (optional)...';
    } else {
        responseGroup.style.display = 'none';
        responseField.required = false;
    }
}

function closeModal() {
    document.getElementById('actionModal').classList.remove('active');
    document.getElementById('actionForm').reset();
    document.getElementById('responseGroup').style.display = 'none';
}
</script>

</body>
</html>
