<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

// Joining complaints with users, departments, and categories
$sql = "SELECT c.*, u.username AS student_username, d.department_name, cat.category_name
        FROM complaints c
        JOIN users u ON c.student_username = u.username
        LEFT JOIN departments d ON c.department_id = d.department_id
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error in prepared statement: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Complaints | Admin</title>
    <link rel="stylesheet" href="style_adminadmin.css"> <link rel="stylesheet" href="style_admins.css">     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3>AdminCMS</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php" class="active"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Master Complaint List</h1>
            <div class="user-pill">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
        </header>

        <section class="content-wrapper">
            <?php 
            $message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
            unset($_SESSION['message']);
            if ($message): 
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
                    <h3>Student Submissions</h3>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
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
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo $row['complaint_id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['title'] ?? 'No title'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($row['complaint'], 0, 60)) . (strlen($row['complaint']) > 60 ? '...' : ''); ?></small>
                                        </td>
                                        <td><span class="student-name"><?php echo htmlspecialchars($row['student_username']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <span class="badge <?php echo strtolower(str_replace('_', '-', $row['status'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                        <td class="action-cell">
                                            <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="action-link respond" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="respond_complaints.php?id=<?php echo $row['complaint_id']; ?>" class="action-link respond" title="Respond">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                            <a href="delete_complaints.php?id=<?php echo $row['complaint_id']; ?>" 
                                               class="action-link delete" 
                                               onclick="return confirm('Delete this complaint record?')"
                                               title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="empty">No complaints found in the system.</td></tr>
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