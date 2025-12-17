<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

require 'connect.php';

// Joining complaints with users to ensure the student exists
$sql = "SELECT c.complaint_id, c.complaint, c.status, c.response, c.created_at, u.username AS student_username
        FROM complaints c
        JOIN users u ON c.student_username = u.username
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
            <div class="card table-card">
                <div class="card-header">
                    <h3>Student Submissions</h3>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Complaint Details</th>
                                <th>Status</th>
                                <th>Teacher Response</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['complaint_id']; ?></td>
                                        <td><span class="student-name"><?php echo htmlspecialchars($row['student_username']); ?></span></td>
                                        <td class="complaint-text"><?php echo htmlspecialchars($row['complaint']); ?></td>
                                        <td>
                                            <span class="badge <?php echo strtolower($row['status']); ?>">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>
                                        <td class="response-text">
                                            <?php echo $row['response'] ? htmlspecialchars($row['response']) : '<em class="no-data">No response yet</em>'; ?>
                                        </td>
                                        <td class="action-cell">
                                            <a href="respond_complaints.php?id=<?php echo $row['complaint_id']; ?>" class="action-link respond">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                            <a href="delete_complaints.php?id=<?php echo $row['complaint_id']; ?>" 
                                               class="action-link delete" 
                                               onclick="return confirm('Delete this complaint record?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty">No complaints found in the system.</td></tr>
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