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
    <link rel="stylesheet" href="style_adminadmin.css">
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
        .btn-primary:hover {
            background: #5568d3 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
        }
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        table tbody tr {
            transition: background-color 0.2s;
        }
        table tbody tr:hover {
            background-color: #f7fafc;
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
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php" class="active"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Manage Departments</h1>
            <div class="user-pill">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
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

            <div class="card" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                <h3 style="margin-top: 0; color: #333; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas <?php echo $editing ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> 
                    <?php echo $editing ? 'Edit Department' : 'Add New Department'; ?>
                </h3>
                <form method="post" style="max-width: 600px;">
                    <?php if ($editing): ?>
                        <input type="hidden" name="department_id" value="<?php echo $editing['department_id']; ?>">
                    <?php endif; ?>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #555;">
                            Department Name <span style="color: #e53e3e;">*</span>
                        </label>
                        <input type="text" name="department_name" value="<?php echo $editing ? htmlspecialchars($editing['department_name']) : ''; ?>" 
                               required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s;"
                               onfocus="this.style.borderColor='#667eea';" onblur="this.style.borderColor='#e2e8f0';">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #555;">Description</label>
                        <textarea name="description" rows="4" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 1rem; font-family: inherit; resize: vertical; transition: border-color 0.2s;"
                                  onfocus="this.style.borderColor='#667eea';" onblur="this.style.borderColor='#e2e8f0';"><?php echo $editing ? htmlspecialchars($editing['description'] ?? '') : ''; ?></textarea>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="<?php echo $editing ? 'update_department' : 'add_department'; ?>" 
                                class="btn btn-primary" style="padding: 0.75rem 1.5rem; background: #667eea; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-save"></i> <?php echo $editing ? 'Update Department' : 'Add Department'; ?>
                        </button>
                        <?php if ($editing): ?>
                            <a href="manage_departments.php" class="btn" style="padding: 0.75rem 1.5rem; background: #e2e8f0; color: #4a5568; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card table-card">
                <h3><i class="fas fa-list"></i> All Departments</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Complaints</th>
                                <th>Officers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($departments): foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo $dept['department_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dept['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo $dept['complaint_count']; ?></td>
                                    <td><?php echo $dept['officer_count']; ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <a href="?edit=<?php echo $dept['department_id']; ?>" 
                                               class="action-link" 
                                               style="color: #667eea; text-decoration: none; padding: 0.5rem; border-radius: 4px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='transparent'"
                                               title="Edit Department">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $dept['department_id']; ?>" 
                                               class="action-link delete" 
                                               style="color: #e53e3e; text-decoration: none; padding: 0.5rem; border-radius: 4px; transition: background 0.2s;"
                                               onmouseover="this.style.background='#fed7d7'" onmouseout="this.style.background='transparent'"
                                               onclick="return confirm('Delete this department? This will fail if it has assigned complaints.')"
                                               title="Delete Department">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="empty">No departments found.</td></tr>
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

