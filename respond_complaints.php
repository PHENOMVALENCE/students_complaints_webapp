<?php
session_start();


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $complaintId = $_GET['id'];

    
    $sql = "SELECT * FROM complaints WHERE complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $complaint = $result->fetch_assoc();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Respond to Complaint #<?php echo $complaint['complaint_id']; ?> - Admin</title>
            <link rel="stylesheet" href="theme.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>
        <body>
        <div class="dashboard-container">
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h3><i class="fas fa-shield-alt"></i> Admin Panel</h3>
                </div>
                <nav class="sidebar-nav">
                    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
                    <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
                    <div class="nav-divider"></div>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </aside>
            <main class="main-content">
                <header class="top-bar">
                    <h1><i class="fas fa-reply"></i> Respond to Complaint</h1>
                    <div class="admin-profile">
                        <i class="fas fa-user-shield"></i>
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </header>
                <section class="content-wrapper">
                    <div class="card" style="max-width: 800px; margin: 0 auto;">
                        <h3><i class="fas fa-file-invoice"></i> Complaint #<?php echo $complaint['complaint_id']; ?></h3>
                        <form action="process_response.php" method="post">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['complaint_id']; ?>">
                            
                            <div class="form-group">
                                <label for="student_username"><i class="fas fa-user"></i> Student Username</label>
                                <input type="text" id="student_username" name="student_username" value="<?php echo htmlspecialchars($complaint['student_username']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label for="complaint"><i class="fas fa-comment-alt"></i> Original Complaint</label>
                                <textarea id="complaint" name="complaint" readonly rows="6"><?php echo htmlspecialchars($complaint['complaint']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="response"><i class="fas fa-reply"></i> Your Response <span class="required">*</span></label>
                                <textarea id="response" name="response" rows="8" placeholder="Enter your response, resolution details, or reason for denial..."></textarea>
                                <small class="form-hint">Provide a clear response. Required when resolving or denying.</small>
                            </div>

                            <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                                <button type="submit" name="action" value="resolve" class="btn-submit" style="background: var(--success, #10b981);">
                                    <i class="fas fa-check-circle"></i> Resolve
                                </button>
                                <button type="submit" name="action" value="deny" class="btn-submit" style="background: var(--danger, #ef4444);">
                                    <i class="fas fa-times-circle"></i> Deny
                                </button>
                                <a href="students_complaints.php" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center;">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
        </div>
        </body>
        </html>
        <?php
    } else {
        echo "Complaint not found.";
        exit;
    }
} else {
    header("Location: students_complaints.php");
    exit;
}
?>
