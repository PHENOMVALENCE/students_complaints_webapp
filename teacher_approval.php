<?php
session_start();

// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Fetch unapproved teachers
// Note: Ensure your 'users' table has the 'approved' column (TINYINT 0 or 1)
$sql = "SELECT user_id, username, role FROM users WHERE role = 'teacher' AND approved = 0";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Approvals | Admin</title>
    <link rel="stylesheet" href="style_adminadmin.css"> <link rel="stylesheet" href="style_users.css">      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Specific tweaks for approval buttons */
        .btn-approve { background: #1cc88a; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.3s; margin-right: 5px; }
        .btn-reject { background: #e74a3b; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .btn-approve:hover, .btn-reject:hover { opacity: 0.8; transform: translateY(-1px); }
        .approval-form { display: inline-flex; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3>AdminCMS</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php" class="active"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Pending Teacher Requests</h1>
            <a href="admin_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </header>

        <section class="content-wrapper">
            <div class="card table-card">
                <div class="card-header">
                    <h3>Review Applications</h3>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Teacher Username</th>
                                <th>Role Path</th>
                                <th>Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['user_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                        <td><span class="role-badge teacher"><?php echo $row['role']; ?></span></td>
                                        <td>
                                            <form action="process_approval.php" method="post" class="approval-form">
                                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                
                                                <button type="submit" name="approve" class="btn-approve">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                
                                                <button type="submit" name="reject" class="btn-reject" onclick="return confirm('Are you sure you want to reject this request?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-msg" style="padding: 40px; text-align: center; color: #718096;">
                                        <i class="fas fa-check-circle" style="font-size: 2rem; color: #1cc88a; display: block; margin-bottom: 10px;"></i>
                                        No pending teacher approvals at this time.
                                    </td>
                                </tr>
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