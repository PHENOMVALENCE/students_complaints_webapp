<?php
session_start();
// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Corrected Query: Using only the fields you confirmed exist.
$sql = "SELECT user_id, username, role FROM users";
$result = $conn->query($sql);

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
                                            <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                                <?php echo htmlspecialchars($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" action="delete_user.php" onsubmit="return confirm('WARNING: Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="btn-delete-user">
                                                    <i class="fas fa-user-times"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-msg">No users found in the database.</td></tr>
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