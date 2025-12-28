<?php
session_start();
// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

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
$sql = "SELECT u.user_id, u.username, u.role, u.department_id, d.department_name 
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
    <link rel="stylesheet" href="style_adminadmin.css"> 
    <link rel="stylesheet" href="style_users.css">
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
        table tbody tr {
            transition: background-color 0.2s;
        }
        table tbody tr:hover {
            background-color: #f7fafc;
        }
        .btn-delete-user {
            transition: all 0.2s;
        }
        .btn-delete-user:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3>AdminCMS</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php" class="active"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>User Account Management</h1>
            <a href="admin_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
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

            <div class="card table-card">
                <div class="card-header">
                    <h3>Registered System Users</h3>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>System Role</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($user = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $user['user_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td>
                                            <span class="role-badge <?php echo strtolower(str_replace('_', '-', $user['role'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'department_officer'): ?>
                                                <form method="post" style="display: inline-block;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <select name="department_id" onchange="this.form.submit()" style="padding: 0.25rem; border: 1px solid #ddd; border-radius: 4px;">
                                                        <option value="">-- No Department --</option>
                                                        <?php foreach ($departments as $dept): ?>
                                                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $user['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" name="assign_department" value="1">
                                                </form>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" action="delete_user.php" onsubmit="return confirm('WARNING: Are you sure you want to delete this user?');" style="display: inline-block;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="btn-delete-user">
                                                    <i class="fas fa-user-times"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-msg">No users found in the database.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

</body>
</html>