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
    die("Error: Department not assigned. Please contact administrator.");
}

$department_id = $user_data['department_id'];

// Get department name
$dept_stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ?");
$dept_stmt->bind_param("i", $department_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$dept_data = $dept_result->fetch_assoc();
$dept_stmt->close();

$department_name = $dept_data['department_name'];

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
    $check_stmt = $conn->prepare("SELECT department_id FROM complaints WHERE complaint_id = ?");
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
            // Get old status for history
            $old_status_stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
            $old_status_stmt->bind_param("i", $complaint_id);
            $old_status_stmt->execute();
            $old_status_result = $old_status_stmt->get_result();
            $old_status_data = $old_status_result->fetch_assoc();
            $old_status = $old_status_data['status'];
            $old_status_stmt->close();
            
            // Update complaint
            $update_stmt = $conn->prepare("UPDATE complaints SET status = ?, response = ?, updated_at = NOW() WHERE complaint_id = ?");
            $update_stmt->bind_param("ssi", $new_status, $response, $complaint_id);
            
            if ($update_stmt->execute()) {
                // Log in history
                $history_notes = $action === 'resolve' ? "Complaint resolved by department officer" : "Status changed to in progress";
                $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $history_stmt->bind_param("issss", $complaint_id, $action, $username, $old_status, $new_status, $history_notes);
                $history_stmt->execute();
                $history_stmt->close();
                
                $_SESSION['message'] = "success|Complaint status updated successfully!";
                header("Location: department_officer_dashboard.php");
                exit;
            } else {
                $message = "error|Update failed: " . $conn->error;
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
    <title>Department Officer Dashboard</title>
    <link rel="stylesheet" href="style_dassh.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h4 {
            margin: 0 0 0.5rem 0;
            color: #666;
            font-size: 0.9rem;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .dept-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .dept-header h2 {
            margin: 0;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .required {
            color: red;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3>CMS Pro</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Department Officer Portal</h1>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            </div>
        </header>

        <section class="content-wrapper">
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

            <div class="dept-header">
                <h2><i class="fas fa-building"></i> <?php echo htmlspecialchars($department_name); ?></h2>
                <p>Manage complaints routed to your department</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Complaints</h4>
                    <div class="number"><?php echo $stats['total']; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Pending</h4>
                    <div class="number" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="stat-card">
                    <h4>In Progress</h4>
                    <div class="number" style="color: #3b82f6;"><?php echo $stats['in_progress']; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Resolved</h4>
                    <div class="number" style="color: #10b981;"><?php echo $stats['resolved']; ?></div>
                </div>
            </div>

            <div class="card table-card">
                <h3><i class="fas fa-list"></i> Department Complaints</h3>
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
                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>&role=officer" class="btn-view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($row['status'] === 'pending' || $row['status'] === 'in_progress'): ?>
                                        <button onclick="showActionForm(<?php echo $row['complaint_id']; ?>, '<?php echo $row['status']; ?>')" class="btn-view" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="empty">No complaints assigned to your department.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Action Modal -->
<div id="actionModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:2rem; border-radius:8px; max-width:500px; width:90%;">
        <h3>Update Complaint Status</h3>
        <form method="post" id="actionForm">
            <input type="hidden" name="complaint_id" id="modal_complaint_id">
            <input type="hidden" name="action" id="modal_action">
            
            <div class="form-group">
                <label>New Status</label>
                <div class="btn-group">
                    <button type="button" onclick="setAction('in_progress')" class="btn btn-primary">Mark In Progress</button>
                    <button type="button" onclick="setAction('resolve')" class="btn btn-success">Resolve</button>
                </div>
            </div>
            
            <div class="form-group" id="responseGroup" style="display:none;">
                <label for="response">Response/Resolution Details <span class="required">*</span></label>
                <textarea id="response" name="response" rows="4" placeholder="Provide details about the resolution..."></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" onclick="closeModal()" class="btn" style="background:#ccc;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showActionForm(complaintId, currentStatus) {
    document.getElementById('modal_complaint_id').value = complaintId;
    document.getElementById('actionModal').style.display = 'flex';
}

function setAction(action) {
    document.getElementById('modal_action').value = action;
    if (action === 'resolve') {
        document.getElementById('responseGroup').style.display = 'block';
        document.getElementById('response').required = true;
    } else {
        document.getElementById('responseGroup').style.display = 'none';
        document.getElementById('response').required = false;
    }
}

function closeModal() {
    document.getElementById('actionModal').style.display = 'none';
    document.getElementById('actionForm').reset();
    document.getElementById('responseGroup').style.display = 'none';
}
</script>

</body>
</html>

