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
            case 'deny':
                if (empty($response)) {
                    $_SESSION['message'] = "error|Please provide a reason when denying a complaint.";
                    header("Location: department_officer_dashboard.php");
                    exit;
                } else {
                    $new_status = 'denied';
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
                $history_notes = $action === 'resolve' ? "Complaint resolved by department officer" : ($action === 'deny' ? "Complaint denied by department officer" : "Status changed to in progress by department officer");
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

// Get search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = ["c.department_id = ?"];
$params = [$department_id];
$param_types = "i";

if (!empty($search_query)) {
    $where_conditions[] = "(c.complaint_id = ? OR c.title LIKE ? OR c.complaint LIKE ? OR u.username LIKE ?)";
    $params[] = (int)$search_query;
    $search_like = "%{$search_query}%";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $param_types .= "isss";
}

if (!empty($filter_status) && in_array($filter_status, ['pending', 'in_progress', 'resolved', 'denied', 'awaiting_student_response'])) {
    $where_conditions[] = "c.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_category > 0) {
    $where_conditions[] = "c.category_id = ?";
    $params[] = $filter_category;
    $param_types .= "i";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(c.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(c.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch complaints for this department
$sql = "SELECT c.*, u.username AS student_username, cat.category_name, d.department_name
        FROM complaints c
        JOIN users u ON c.student_username = u.username
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        $where_clause
        ORDER BY FIELD(c.status, 'pending', 'awaiting_student_response', 'in_progress', 'resolved', 'denied'), c.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$complaints = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories for filter
$categories = $conn->query("SELECT * FROM complaint_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

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
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
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
                
                <!-- Search and Filter Form -->
                <form method="get" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-lg); background: var(--bg-light); border-radius: var(--radius);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                        <div>
                            <label style="display: block; margin-bottom: var(--spacing-xs); font-weight: 600; font-size: 0.875rem;">Search</label>
                            <input type="text" name="search" placeholder="ID, title, student..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 100%; padding: var(--spacing-sm); border: 2px solid var(--border); border-radius: var(--radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: var(--spacing-xs); font-weight: 600; font-size: 0.875rem;">Status</label>
                            <select name="status" style="width: 100%; padding: var(--spacing-sm); border: 2px solid var(--border); border-radius: var(--radius);">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="awaiting_student_response" <?php echo $filter_status === 'awaiting_student_response' ? 'selected' : ''; ?>>Awaiting Response</option>
                                <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="denied" <?php echo $filter_status === 'denied' ? 'selected' : ''; ?>>Denied</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: var(--spacing-xs); font-weight: 600; font-size: 0.875rem;">Category</label>
                            <select name="category" style="width: 100%; padding: var(--spacing-sm); border: 2px solid var(--border); border-radius: var(--radius);">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php echo $filter_category == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: var(--spacing-xs); font-weight: 600; font-size: 0.875rem;">Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="width: 100%; padding: var(--spacing-sm); border: 2px solid var(--border); border-radius: var(--radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: var(--spacing-xs); font-weight: 600; font-size: 0.875rem;">Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="width: 100%; padding: var(--spacing-sm); border: 2px solid var(--border); border-radius: var(--radius);">
                        </div>
                    </div>
                    <div style="display: flex; gap: var(--spacing-sm);">
                        <button type="submit" class="btn-submit" style="flex: 1;">
                            <i class="fas fa-search"></i> Search & Filter
                        </button>
                        <a href="department_officer_dashboard.php" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; padding: var(--spacing-md) var(--spacing-lg);">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="datatable datatable-desc">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Student</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th data-orderable="false">Actions</th>
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
                                <td>
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                    <?php if ($row['attachment_count'] > 0): ?>
                                        <br><small style="color: var(--primary); display: flex; align-items: center; gap: 4px; margin-top: 4px;"><i class="fas fa-paperclip"></i> <?php echo $row['attachment_count']; ?> file(s)</small>
                                    <?php endif; ?>
                                    <?php if ($row['pending_requests'] > 0): ?>
                                        <br><small style="color: var(--warning); display: flex; align-items: center; gap: 4px; margin-top: 4px;"><i class="fas fa-question-circle"></i> <?php echo $row['pending_requests']; ?> request(s)</small>
                                    <?php endif; ?>
                                    <?php if ($row['note_count'] > 0): ?>
                                        <br><small style="color: var(--info); display: flex; align-items: center; gap: 4px; margin-top: 4px;"><i class="fas fa-comments"></i> <?php echo $row['note_count']; ?> note(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: var(--spacing-sm); align-items: center;">
                                        <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($row['status'] === 'pending' || $row['status'] === 'in_progress' || $row['status'] === 'awaiting_student_response'): ?>
                                            <button onclick="showActionForm(<?php echo $row['complaint_id']; ?>, '<?php echo $row['status']; ?>')" class="btn-view" title="Update Status" style="background: none; border: none; cursor: pointer;">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
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
                    <button type="button" onclick="setAction('deny')" class="btn-action btn-deny" style="background: #fee2e2; color: #dc2626;">
                        <i class="fas fa-times"></i> Deny
                    </button>
                </div>
            </div>
            
            <div id="responseGroup" style="display:none; margin-bottom: var(--spacing-lg);">
                <label for="response" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600;">Response / Reason <span class="required">*</span></label>
                <textarea id="response" name="response" class="response-textarea" rows="6" placeholder="Provide resolution details or reason for denial..."></textarea>
                <small class="form-hint">This response will be visible to the student when the complaint is resolved or denied.</small>
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
    } else if (action === 'deny') {
        responseGroup.style.display = 'block';
        responseField.required = true;
        responseField.placeholder = 'Provide reason for denial...';
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

<?php require_once 'includes/datatables.inc.php'; ?>
</body>
</html>
