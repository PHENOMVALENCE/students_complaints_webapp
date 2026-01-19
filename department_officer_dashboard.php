<?php
session_start();

// Security Check - Only department officers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'department_officer') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Get department officer's department
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data || !$user_data['department_id']) {
    $_SESSION['message'] = "error|Department not assigned. Please contact administrator.";
    header("Location: index.php");
    exit;
}

$department_id = $user_data['department_id'];

// Get department name
$dept_stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ?");
$dept_stmt->bind_param("i", $department_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$dept_data = $dept_result->fetch_assoc();
$dept_stmt->close();

$department_name = $dept_data['department_name'] ?? 'Unknown Department';

// Get message from session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
unset($_SESSION['message']);

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $action = $_POST['action'];
    $response = trim($_POST['response'] ?? '');
    $new_status = '';
    
    // Validate that complaint belongs to this department
    $check_stmt = $conn->prepare("SELECT status, department_id FROM complaints WHERE complaint_id = ?");
    $check_stmt->bind_param("i", $complaint_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if (!$check_data || $check_data['department_id'] != $department_id) {
        $_SESSION['message'] = "error|Unauthorized: This complaint does not belong to your department.";
        header("Location: department_officer_dashboard.php");
        exit;
    } else {
        $old_status = $check_data['status'] ?? 'pending';
        
        switch ($action) {
            case 'in_progress':
                $new_status = 'in_progress';
                break;
            case 'resolve':
                if (empty($response)) {
                    $_SESSION['message'] = "error|Please provide a response when resolving a complaint.";
                    header("Location: department_officer_dashboard.php");
                    exit;
                } else {
                    $new_status = 'resolved';
                }
                break;
            default:
                $_SESSION['message'] = "error|Invalid action.";
                header("Location: department_officer_dashboard.php");
                exit;
        }
        
        if ($new_status) {
            // Update complaint
            $update_stmt = $conn->prepare("UPDATE complaints SET status = ?, response = ?, updated_at = NOW() WHERE complaint_id = ?");
            $update_stmt->bind_param("ssi", $new_status, $response, $complaint_id);
            
            if ($update_stmt->execute()) {
                // Log in history
                $history_notes = $action === 'resolve' ? "Complaint resolved by department officer" : "Status changed to in progress by department officer";
                $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $history_stmt->bind_param("isssss", $complaint_id, $action, $username, $old_status, $new_status, $history_notes);
                $history_stmt->execute();
                $history_stmt->close();
                
                $_SESSION['message'] = "success|Complaint status updated successfully!";
                header("Location: department_officer_dashboard.php");
                exit;
            } else {
                $_SESSION['message'] = "error|Update failed: " . $conn->error;
                header("Location: department_officer_dashboard.php");
                exit;
            }
            $update_stmt->close();
        }
    }
}

// Fetch complaints for this department
$stmt = $conn->prepare("SELECT c.*, u.username AS student_username, cat.category_name, d.department_name
                        FROM complaints c
                        JOIN users u ON c.student_username = u.username
                        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                        LEFT JOIN departments d ON c.department_id = d.department_id
                        WHERE c.department_id = ?
                        ORDER BY FIELD(c.status, 'pending', 'in_progress', 'resolved'), c.created_at DESC");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
$complaints = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints WHERE department_id = ?");
$stats_stmt->bind_param("i", $department_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Officer Dashboard - CMS</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dept-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
        }
        
        .dept-banner h2 {
            color: white;
            margin-bottom: var(--spacing-sm);
            font-size: 2rem;
        }
        
        .dept-banner p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 1.1rem;
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
            <h3><i class="fas fa-building"></i> Department Portal</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="department_officer_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="department_officer_dashboard.php#complaints"><i class="fas fa-list-alt"></i> My Complaints</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-tachometer-alt"></i> Department Officer Dashboard</h1>
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

            <div class="dept-banner">
                <h2><i class="fas fa-building"></i> <?php echo htmlspecialchars($department_name); ?></h2>
                <p>Manage complaints routed to your department</p>
            </div>

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

            <div class="card" id="complaints">
                <h3><i class="fas fa-list-alt"></i> Department Complaints</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Student</th>
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
                                    <div style="display: flex; gap: var(--spacing-sm); align-items: center;">
                                        <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($row['status'] === 'pending' || $row['status'] === 'in_progress'): ?>
                                            <button onclick="showActionForm(<?php echo $row['complaint_id']; ?>, '<?php echo $row['status']; ?>')" class="btn-view" title="Update Status" style="background: none; border: none; cursor: pointer;">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="empty">No complaints assigned to your department yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Action Modal -->
<div id="actionModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-top: 0;"><i class="fas fa-edit"></i> Update Complaint Status</h3>
        <form method="post" id="actionForm">
            <input type="hidden" name="complaint_id" id="modal_complaint_id">
            <input type="hidden" name="action" id="modal_action">
            
            <div style="margin-bottom: var(--spacing-lg);">
                <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600;">New Status</label>
                <div class="action-buttons" style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
                    <button type="button" onclick="setAction('in_progress')" class="btn-action btn-in-progress">
                        <i class="fas fa-spinner"></i> Mark In Progress
                    </button>
                    <button type="button" onclick="setAction('resolve')" class="btn-action btn-resolve">
                        <i class="fas fa-check"></i> Resolve
                    </button>
                </div>
            </div>
            
            <div id="responseGroup" style="display:none; margin-bottom: var(--spacing-lg);">
                <label for="response" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600;">Response/Resolution Details <span class="required">*</span></label>
                <textarea id="response" name="response" class="response-textarea" rows="6" placeholder="Provide details about the resolution..."></textarea>
                <small class="form-hint">This response will be visible to the student when the complaint is resolved.</small>
            </div>
            
            <div style="display: flex; gap: var(--spacing-md);">
                <button type="submit" class="btn-submit" style="flex: 1;">
                    <i class="fas fa-save"></i> Update Status
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; gap: var(--spacing-sm);">
                    <i class="fas fa-times"></i> Cancel
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
