<?php
session_start();


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $complaintId = $_GET['id'];

    // Fetch complaint with related data
    $sql = "SELECT c.*, d.department_name, cat.category_name 
            FROM complaints c
            LEFT JOIN departments d ON c.department_id = d.department_id
            LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
            WHERE c.complaint_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $complaint = $result->fetch_assoc();
        $stmt->close();
        
        // Fetch attachments
        $attach_stmt = $conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC");
        $attach_stmt->bind_param("i", $complaintId);
        $attach_stmt->execute();
        $attach_result = $attach_stmt->get_result();
        $attachments = $attach_result->fetch_all(MYSQLI_ASSOC);
        $attach_stmt->close();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Respond to Complaint #<?php echo $complaint['complaint_id']; ?> - Admin</title>
            <link rel="stylesheet" href="assets/css/theme.css">
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
                    <div style="margin-bottom: var(--spacing-md);">
                        <a href="view_complaint_detail.php?id=<?php echo $complaint['complaint_id']; ?>" class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: var(--spacing-sm);">
                            <i class="fas fa-eye"></i> View Full Details
                        </a>
                    </div>
                    
                    <div class="card" style="max-width: 800px; margin: 0 auto;">
                        <h3><i class="fas fa-file-invoice"></i> Complaint #<?php echo $complaint['complaint_id']; ?></h3>
                        
                        <div style="background: var(--info-light); padding: var(--spacing-md); border-radius: var(--radius); margin-bottom: var(--spacing-lg); border-left: 4px solid var(--info);">
                            <strong><i class="fas fa-info-circle"></i> Quick Response Form</strong>
                            <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.875rem; color: var(--text-secondary);">
                                For complete complaint details including attachments, collaboration notes, and history, use the "View Full Details" button above.
                            </p>
                        </div>
                        
                        <form action="handlers/process_response.php" method="post">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['complaint_id']; ?>">
                            
                            <div class="form-group">
                                <label for="student_username"><i class="fas fa-user"></i> Student Username</label>
                                <input type="text" id="student_username" name="student_username" value="<?php echo htmlspecialchars($complaint['student_username']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($complaint['department_name'] ?? 'N/A'); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Category</label>
                                <input type="text" value="<?php echo htmlspecialchars($complaint['category_name'] ?? 'Uncategorized'); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label for="complaint"><i class="fas fa-comment-alt"></i> Original Complaint</label>
                                <textarea id="complaint" name="complaint" readonly rows="6"><?php echo htmlspecialchars($complaint['complaint']); ?></textarea>
                            </div>
                            
                            <?php if (!empty($attachments)): ?>
                            <div class="form-group">
                                <label><i class="fas fa-paperclip"></i> Attachments (<?php echo count($attachments); ?>)</label>
                                <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); margin-top: var(--spacing-xs);">
                                    <?php foreach ($attachments as $attach): ?>
                                        <a href="handlers/download_attachment.php?id=<?php echo $attach['attachment_id']; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: var(--spacing-xs); padding: var(--spacing-sm) var(--spacing-md); background: var(--bg-light); border: 1px solid var(--border); border-radius: var(--radius); text-decoration: none; color: var(--text-primary); font-size: 0.875rem;">
                                            <i class="fas <?php echo $attach['file_type'] === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image'; ?>" style="color: <?php echo $attach['file_type'] === 'application/pdf' ? '#dc2626' : '#10b981'; ?>;"></i>
                                            <span><?php echo htmlspecialchars($attach['file_name']); ?></span>
                                            <i class="fas fa-external-link-alt" style="font-size: 0.75rem; opacity: 0.7;"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <small class="form-hint">Click to view/download files</small>
                            </div>
                            <?php endif; ?>

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
