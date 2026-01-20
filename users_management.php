<?php
session_start();
// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Handle role change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    // Prevent changing admin role
    $check_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $user_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($user_data['role'] === 'admin') {
        $_SESSION['message'] = "error|Cannot change admin role.";
        header("Location: users_management.php");
        exit;
    }
    
    $valid_roles = ['student', 'teacher', 'department_officer'];
    if (!in_array($new_role, $valid_roles)) {
        $_SESSION['message'] = "error|Invalid role selected.";
        header("Location: users_management.php");
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|User role updated successfully!";
        header("Location: users_management.php");
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to update role: " . $conn->error;
        header("Location: users_management.php");
        exit;
    }
    $stmt->close();
}

// Handle department assignment
$message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
unset($_SESSION['message']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_department'])) {
    $user_id = (int)$_POST['user_id'];
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    
    $stmt = $conn->prepare("UPDATE users SET department_id = ? WHERE user_id = ?");
    $stmt->bind_param("ii", $department_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Department assigned successfully!";
        header("Location: users_management.php");
        exit;
    } else {
        $_SESSION['message'] = "error|Failed to assign department: " . $conn->error;
        header("Location: users_management.php");
        exit;
    }
    $stmt->close();
}

// Fetch users with department information
$sql = "SELECT u.user_id, u.username, u.role, u.department_id, u.approved, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        ORDER BY u.user_id DESC";
$result = $conn->query($sql);

// Fetch departments for dropdown
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);

// Handle Query Failure
if (!$result) {
    die("Database Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Admin</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-card {
            background: var(--bg-white);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--primary);
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-info h4 {
            margin: 0 0 var(--spacing-xs) 0;
            color: var(--text-primary);
        }
        
        .user-meta {
            display: flex;
            gap: var(--spacing-lg);
            flex-wrap: wrap;
            margin-top: var(--spacing-sm);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .user-actions {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        
        .action-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }
        
        .action-group label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-group select {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9rem;
            transition: var(--transition);
            min-width: 180px;
        }
        
        .action-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-delete-user {
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--danger);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.875rem;
        }
        
        .btn-delete-user:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .approval-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .approval-badge.approved {
            background: var(--success-light);
            color: #065f46;
        }
        
        .approval-badge.pending {
            background: var(--warning-light);
            color: #92400e;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-mini {
            background: var(--bg-white);
            padding: var(--spacing-md);
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-mini .number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-mini .label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: var(--spacing-xs);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-shield-alt"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php" class="active"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-users-cog"></i> User Account Management</h1>
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

            <?php
            // Get user statistics
            $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
            $total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
            $total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
            $total_officers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'department_officer'")->fetch_assoc()['count'];
            ?>
            
            <div class="stats-summary">
                <div class="stat-mini">
                    <div class="number"><?php echo $total_users; ?></div>
                    <div class="label">Total Users</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo $total_students; ?></div>
                    <div class="label">Students</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo $total_teachers; ?></div>
                    <div class="label">Teachers</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo $total_officers; ?></div>
                    <div class="label">Officers</div>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-users"></i> All System Users</h3>
                
                <?php if ($result->num_rows > 0): ?>
                    <?php while($user = $result->fetch_assoc()): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-info">
                                <h4>
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                                    <span class="role-badge <?php echo strtolower(str_replace('_', '-', $user['role'])); ?>" style="margin-left: var(--spacing-sm);">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                    <?php if ($user['role'] === 'teacher'): ?>
                                        <span class="approval-badge <?php echo $user['approved'] ? 'approved' : 'pending'; ?>">
                                            <i class="fas <?php echo $user['approved'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                            <?php echo $user['approved'] ? 'Approved' : 'Pending Approval'; ?>
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <div class="user-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-id-badge"></i>
                                        <span>ID: #<?php echo $user['user_id']; ?></span>
                                    </div>
                                    <?php if ($user['department_name']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-building"></i>
                                        <span><?php echo htmlspecialchars($user['department_name']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="user-actions">
                                <?php if ($user['role'] !== 'admin'): ?>
                                <div class="action-group">
                                    <label><i class="fas fa-user-tag"></i> Change Role</label>
                                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to change this user\'s role?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <select name="new_role" onchange="this.form.submit()">
                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="teacher" <?php echo $user['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                            <option value="department_officer" <?php echo $user['role'] === 'department_officer' ? 'selected' : ''; ?>>Department Officer</option>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] === 'teacher' || $user['role'] === 'department_officer'): ?>
                                <div class="action-group">
                                    <label><i class="fas fa-building"></i> Assign Department</label>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <select name="department_id" onchange="this.form.submit()">
                                            <option value="">-- No Department --</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['department_id']; ?>" <?php echo $user['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="assign_department" value="1">
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] !== 'admin'): ?>
                                <form method="post" action="delete_user.php" onsubmit="return confirm('WARNING: Are you sure you want to delete this user? This action cannot be undone.');" style="margin-top: var(--spacing-sm);">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="btn-delete-user">
                                        <i class="fas fa-user-times"></i> Delete User
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="text-align: center; padding: var(--spacing-2xl);">
                        <i class="fas fa-users" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></i>
                        <h3>No users found</h3>
                        <p>No users are registered in the system yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

</body>
</html>
