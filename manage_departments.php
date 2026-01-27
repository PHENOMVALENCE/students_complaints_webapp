<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';
$message = "";

// Handle Add Department
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    $dept_name = trim($_POST['department_name']);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($dept_name)) {
        $_SESSION['message'] = "error|Department name is required.";
        header("Location: manage_departments.php");
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $dept_name, $description);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Department added successfully!";
            header("Location: manage_departments.php");
            exit;
        } else {
            $_SESSION['message'] = "error|Failed to add department: " . $conn->error;
            header("Location: manage_departments.php");
            exit;
        }
        $stmt->close();
    }
}

// Handle Update Department
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_department'])) {
    $dept_id = (int)$_POST['department_id'];
    $dept_name = trim($_POST['department_name']);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($dept_name)) {
        $_SESSION['message'] = "error|Department name is required.";
        header("Location: manage_departments.php");
        exit;
    } else {
        $stmt = $conn->prepare("UPDATE departments SET department_name = ?, description = ? WHERE department_id = ?");
        $stmt->bind_param("ssi", $dept_name, $description, $dept_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Department updated successfully!";
            header("Location: manage_departments.php");
            exit;
        } else {
            $_SESSION['message'] = "error|Failed to update department: " . $conn->error;
            header("Location: manage_departments.php");
            exit;
        }
        $stmt->close();
    }
}

// Handle Delete Department
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $dept_id = (int)$_GET['delete'];
    
    // Check if department has complaints
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE department_id = ?");
    $check_stmt->bind_param("i", $dept_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($check_data['count'] > 0) {
        $_SESSION['message'] = "error|Cannot delete department with assigned complaints. Reassign complaints first.";
        header("Location: manage_departments.php");
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM departments WHERE department_id = ?");
        $stmt->bind_param("i", $dept_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Department deleted successfully!";
            header("Location: manage_departments.php");
            exit;
        } else {
            $_SESSION['message'] = "error|Failed to delete department: " . $conn->error;
            header("Location: manage_departments.php");
            exit;
        }
        $stmt->close();
    }
}

// Get message from session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
unset($_SESSION['message']);

// Check if editing
$editing = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM departments WHERE department_id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $editing = $edit_result->fetch_assoc();
    }
    $edit_stmt->close();
}

// Fetch all departments
$departments = $conn->query("SELECT d.*, 
    (SELECT COUNT(*) FROM complaints WHERE department_id = d.department_id) as complaint_count,
    (SELECT COUNT(*) FROM users WHERE department_id = d.department_id AND role = 'department_officer') as officer_count
    FROM departments d 
    ORDER BY d.department_name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments | Admin</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-shield-alt"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php" class="active"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-building"></i> Manage Departments</h1>
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

            <div class="card">
                <h3><i class="fas <?php echo $editing ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> <?php echo $editing ? 'Edit Department' : 'Add New Department'; ?></h3>
                <form method="post" style="max-width: 700px;">
                    <?php if ($editing): ?>
                        <input type="hidden" name="department_id" value="<?php echo $editing['department_id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="department_name"><i class="fas fa-building"></i> Department Name <span class="required">*</span></label>
                        <input type="text" id="department_name" name="department_name" value="<?php echo $editing ? htmlspecialchars($editing['department_name']) : ''; ?>" required placeholder="e.g., Information Technology">
                    </div>
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" rows="4" placeholder="Brief description of the department's responsibilities..."><?php echo $editing ? htmlspecialchars($editing['description'] ?? '') : ''; ?></textarea>
                    </div>
                    <div style="display: flex; gap: var(--spacing-md);">
                        <button type="submit" name="<?php echo $editing ? 'update_department' : 'add_department'; ?>" class="btn-submit" style="width: auto; padding: var(--spacing-md) var(--spacing-xl);">
                            <i class="fas fa-save"></i> <?php echo $editing ? 'Update Department' : 'Add Department'; ?>
                        </button>
                        <?php if ($editing): ?>
                            <a href="manage_departments.php" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; gap: var(--spacing-sm);">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-list"></i> All Departments (<?php echo count($departments); ?>)</h3>
                <div class="table-responsive">
                    <table class="datatable datatable-desc">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Complaints</th>
                                <th>Officers</th>
                                <th data-orderable="false">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($departments): foreach ($departments as $dept): ?>
                                <tr>
                                    <td><strong>#<?php echo $dept['department_id']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dept['description'] ?? 'No description'); ?></td>
                                    <td>
                                        <span class="badge" style="background: var(--info-light); color: #1e40af;">
                                            <i class="fas fa-file-invoice"></i> <?php echo $dept['complaint_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--success-light); color: #065f46;">
                                            <i class="fas fa-users"></i> <?php echo $dept['officer_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: var(--spacing-sm);">
                                            <a href="?edit=<?php echo $dept['department_id']; ?>" class="btn-view" title="Edit Department">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $dept['department_id']; ?>" class="btn-delete" onclick="return confirm('Delete this department? This will fail if it has assigned complaints or officers.')" title="Delete Department">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

<?php require_once 'includes/datatables.inc.php'; ?>
</body>
</html>
