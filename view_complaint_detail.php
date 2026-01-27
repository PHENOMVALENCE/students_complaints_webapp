<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['role'];

if (!$complaint_id) {
    die("Invalid complaint ID");
}

// Fetch complaint with all related data
$stmt = $conn->prepare("SELECT c.*, u.username AS student_username, 
                        d.department_name, cat.category_name,
                        (SELECT COUNT(*) FROM complaint_history WHERE complaint_id = c.complaint_id) as history_count
                        FROM complaints c
                        JOIN users u ON c.student_username = u.username
                        LEFT JOIN departments d ON c.department_id = d.department_id
                        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                        WHERE c.complaint_id = ?");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Complaint not found");
}

$complaint = $result->fetch_assoc();
$stmt->close();

// Security check: Students can only view their own complaints
if ($role === 'student' && $complaint['student_username'] !== $_SESSION['username']) {
    die("Unauthorized: You can only view your own complaints.");
}

// Security check: Department officers can only view complaints from their department
if ($role === 'department_officer') {
    $user_stmt = $conn->prepare("SELECT department_id FROM users WHERE username = ?");
    $user_stmt->bind_param("s", $_SESSION['username']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data || $user_data['department_id'] != $complaint['department_id']) {
        die("Unauthorized: This complaint does not belong to your department.");
    }
}

// Fetch complaint history
$history_stmt = $conn->prepare("SELECT * FROM complaint_history WHERE complaint_id = ? ORDER BY created_at ASC");
$history_stmt->bind_param("i", $complaint_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

// Fetch attachments
$attach_stmt = $conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC");
$attach_stmt->bind_param("i", $complaint_id);
$attach_stmt->execute();
$attach_result = $attach_stmt->get_result();
$attachments = $attach_result->fetch_all(MYSQLI_ASSOC);
$attach_stmt->close();

// Fetch feedback if resolved
$feedback = null;
if ($complaint['status'] === 'resolved') {
    $feedback_stmt = $conn->prepare("SELECT * FROM complaint_feedback WHERE complaint_id = ?");
    $feedback_stmt->bind_param("i", $complaint_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    $feedback = $feedback_result->fetch_assoc();
    $feedback_stmt->close();
}

// Fetch collaboration notes (staff/admin only)
$collab_notes = [];
if (in_array($role, ['admin', 'department_officer', 'teacher'])) {
    $notes_stmt = $conn->prepare("SELECT cn.*, u.username FROM collaboration_notes cn JOIN users u ON cn.created_by = u.username WHERE cn.complaint_id = ? ORDER BY cn.created_at DESC");
    $notes_stmt->bind_param("i", $complaint_id);
    $notes_stmt->execute();
    $notes_result = $notes_stmt->get_result();
    $collab_notes = $notes_result->fetch_all(MYSQLI_ASSOC);
    $notes_stmt->close();
}

// Fetch information requests (all roles: staff see them, students must see to respond)
$info_requests = [];
$req_stmt = $conn->prepare("SELECT ir.*, u.username FROM information_requests ir JOIN users u ON ir.requested_by = u.username WHERE ir.complaint_id = ? ORDER BY ir.created_at DESC");
$req_stmt->bind_param("i", $complaint_id);
$req_stmt->execute();
$req_result = $req_stmt->get_result();
$info_requests = $req_result->fetch_all(MYSQLI_ASSOC);
$req_stmt->close();

$has_pending_request = false;
foreach ($info_requests as $ir) {
    if ($ir['status'] === 'pending') {
        $has_pending_request = true;
        break;
    }
}

// Determine if student name should be shown (admin always sees it, staff only if not anonymous)
$show_student_name = ($role === 'admin' || $role === 'student' || !($complaint['is_anonymous'] ?? 0));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details #<?php echo $complaint_id; ?> - CMS</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .detail-card {
            background: var(--bg-white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-lg);
        }
        .detail-header {
            border-bottom: 2px solid var(--border);
            padding-bottom: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .detail-header h2 {
            margin: 0;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .detail-value {
            color: var(--text-primary);
        }
        .history-item {
            padding: var(--spacing-md);
            border-left: 4px solid var(--primary);
            margin-bottom: var(--spacing-md);
            background: var(--bg-light);
            border-radius: var(--radius);
        }
        .history-item .history-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-sm);
        }
        .history-item .history-action {
            font-weight: 600;
            color: var(--primary);
        }
        .history-item .history-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: var(--radius);
            transition: var(--transition);
        }
        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateX(-4px);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-file-alt"></i> Complaint Details</h3>
        </div>
        <nav class="sidebar-nav">
            <?php if ($role === 'student'): ?>
                <a href="track_complaints.php"><i class="fas fa-list-alt"></i> Back to My Complaints</a>
                <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'teacher'): ?>
                <a href="teacher_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <?php elseif ($role === 'admin'): ?>
                <a href="students_complaints.php"><i class="fas fa-arrow-left"></i> Back to Complaints</a>
            <?php else: ?>
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <?php endif; ?>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>
    <main class="main-content">
        <section class="content-wrapper">
            <div class="detail-container">

                <?php if ($role === 'student' && $has_pending_request): ?>
                <div class="detail-card" id="action-required" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 6px solid var(--warning); margin-bottom: var(--spacing-lg);">
                    <h3 style="margin: 0 0 var(--spacing-sm) 0; color: #92400e;"><i class="fas fa-exclamation-circle"></i> Action Required</h3>
                    <p style="margin: 0 0 var(--spacing-md) 0; color: #78350f;">Staff have requested additional information for this complaint. Please scroll down to the <strong>Information Requests</strong> section and submit your response.</p>
                    <a href="#info-requests" style="display: inline-flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-sm) var(--spacing-lg); background: var(--warning); color: white; border-radius: var(--radius); text-decoration: none; font-weight: 600; transition: var(--transition);" onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='var(--warning)'">
                        <i class="fas fa-arrow-down"></i> Go to Response Form
                    </a>
                </div>
                <?php endif; ?>

                <div class="detail-card">
                    <div class="detail-header">
                        <h2><i class="fas fa-file-invoice"></i> Complaint #<?php echo $complaint['complaint_id']; ?></h2>
                        <span class="status-badge <?php echo strtolower(str_replace('_', '-', $complaint['status'])); ?>">
                            <i class="fas <?php 
                                if ($complaint['status'] === 'pending') echo 'fa-clock';
                                elseif ($complaint['status'] === 'in_progress') echo 'fa-spinner';
                                elseif ($complaint['status'] === 'awaiting_student_response') echo 'fa-user-check';
                                elseif ($complaint['status'] === 'resolved') echo 'fa-check-circle';
                                else echo 'fa-times-circle';
                            ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Title:</div>
                        <div class="detail-value"><strong><?php echo htmlspecialchars($complaint['title'] ?? 'No title'); ?></strong></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Student:</div>
                        <div class="detail-value">
                            <?php if ($show_student_name): ?>
                                <?php echo htmlspecialchars($complaint['student_username']); ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-style: italic;">
                                    <i class="fas fa-user-secret"></i> Anonymous
                                </span>
                            <?php endif; ?>
                            <?php if (($complaint['is_anonymous'] ?? 0) && $role === 'admin'): ?>
                                <small style="color: var(--text-secondary); margin-left: var(--spacing-sm);">
                                    (Anonymous complaint - Admin view)
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Department:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['department_name'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['category_name'] ?? 'Uncategorized'); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Submitted:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($complaint['created_at'])); ?></div>
                    </div>

                    <?php if ($complaint['routed_at']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Routed:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($complaint['routed_at'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($complaint['updated_at']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Last Updated:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($complaint['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row" style="grid-template-columns: 1fr;">
                        <div>
                            <div class="detail-label">Description:</div>
                            <div class="detail-value" style="margin-top: 0.5rem; white-space: pre-wrap;"><?php echo htmlspecialchars($complaint['complaint']); ?></div>
                        </div>
                    </div>

                    <?php if ($complaint['response']): ?>
                    <div class="detail-row" style="grid-template-columns: 1fr;">
                        <div>
                            <div class="detail-label"><i class="fas fa-reply"></i> Response/Resolution:</div>
                            <div class="detail-value" style="margin-top: var(--spacing-sm); white-space: pre-wrap; background: var(--info-light); padding: var(--spacing-md); border-radius: var(--radius); border-left: 4px solid var(--info);"><?php echo htmlspecialchars($complaint['response']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($attachments)): ?>
                    <div class="detail-row" style="grid-template-columns: 1fr;">
                        <div>
                            <div class="detail-label"><i class="fas fa-paperclip"></i> Attachments (<?php echo count($attachments); ?>):</div>
                            <div class="detail-value" style="margin-top: var(--spacing-sm);">
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: var(--spacing-md);">
                                    <?php foreach ($attachments as $attach): ?>
                                        <a href="download_attachment.php?id=<?php echo $attach['attachment_id']; ?>" target="_blank" style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-md); background: var(--bg-light); border-radius: var(--radius); text-decoration: none; color: var(--text-primary); transition: var(--transition); border: 1px solid var(--border); hover:border-color: var(--primary);" onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                            <i class="fas <?php echo $attach['file_type'] === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image'; ?>" style="color: <?php echo $attach['file_type'] === 'application/pdf' ? '#dc2626' : '#10b981'; ?>; font-size: 1.5rem; flex-shrink: 0;"></i>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 2px;" title="<?php echo htmlspecialchars($attach['file_name']); ?>"><?php echo htmlspecialchars($attach['file_name']); ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-secondary); display: flex; align-items: center; gap: var(--spacing-xs);">
                                                    <span><?php echo number_format($attach['file_size'] / 1024, 2); ?> KB</span>
                                                    <span>•</span>
                                                    <span><?php echo date('M d, Y', strtotime($attach['uploaded_at'])); ?></span>
                                                </div>
                                            </div>
                                            <i class="fas fa-download" style="color: var(--primary); flex-shrink: 0;"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($complaint['status'] === 'resolved' && $role === 'student' && !$feedback): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-star"></i> Provide Feedback</h3>
                    <form action="submit_feedback.php" method="post">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                        <div class="form-group">
                            <label>Rating <span class="required">*</span></label>
                            <div style="display: flex; gap: var(--spacing-sm); margin-bottom: var(--spacing-md);">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label style="cursor: pointer; font-size: 2rem; color: #d1d5db; transition: var(--transition);">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;" onchange="this.parentElement.style.color='#fbbf24'; document.querySelectorAll('input[name=rating]').forEach(r => { if (r.value <= this.value) r.parentElement.style.color='#fbbf24'; else r.parentElement.style.color='#d1d5db'; });">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feedback_text">Feedback Comments</label>
                            <textarea id="feedback_text" name="feedback_text" rows="4" placeholder="Share your thoughts about how this complaint was handled..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </form>
                </div>
                <?php elseif ($feedback): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-star"></i> Student Feedback</h3>
                    <div style="display: flex; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                        <div style="display: flex; gap: 2px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= $feedback['rating'] ? '#fbbf24' : '#d1d5db'; ?>;"></i>
                            <?php endfor; ?>
                        </div>
                        <span style="color: var(--text-secondary); font-size: 0.875rem;">
                            Submitted: <?php echo date('M d, Y', strtotime($feedback['submitted_at'])); ?>
                        </span>
                    </div>
                    <?php if ($feedback['feedback_text']): ?>
                        <div style="background: var(--bg-light); padding: var(--spacing-md); border-radius: var(--radius); white-space: pre-wrap;"><?php echo htmlspecialchars($feedback['feedback_text']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'department_officer', 'teacher'])): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-comments"></i> Collaboration Notes (Internal)</h3>
                    
                    <?php if (!empty($collab_notes)): ?>
                        <?php foreach ($collab_notes as $note): ?>
                            <div style="padding: var(--spacing-md); background: var(--bg-light); border-left: 4px solid var(--primary); border-radius: var(--radius); margin-bottom: var(--spacing-md);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                                    <strong><?php echo htmlspecialchars($note['username']); ?></strong>
                                    <span style="color: var(--text-secondary); font-size: 0.875rem;"><?php echo date('M d, Y g:i A', strtotime($note['created_at'])); ?></span>
                                </div>
                                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($note['note_text']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-style: italic;">No collaboration notes yet.</p>
                    <?php endif; ?>
                    
                    <form action="add_collaboration_note.php" method="post" style="margin-top: var(--spacing-lg);">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                        <div class="form-group">
                            <label for="note_text">Add Internal Note</label>
                            <textarea id="note_text" name="note_text" rows="3" placeholder="Add a note for other staff members..." required></textarea>
                            <small class="form-hint">This note will only be visible to staff and administrators, not to the student.</small>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Note
                        </button>
                    </form>
                </div>
                
                <div class="detail-card">
                    <h3><i class="fas fa-question-circle"></i> Request Information from Student</h3>
                    <form action="request_information.php" method="post">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                        <div class="form-group">
                            <label for="request_message">Request Message <span class="required">*</span></label>
                            <textarea id="request_message" name="request_message" rows="4" placeholder="Specify what additional information you need from the student..." required></textarea>
                            <small class="form-hint">This will change the complaint status to "Awaiting Student Response" and notify the student.</small>
                        </div>
                        <button type="submit" class="btn-submit" style="background: var(--warning);">
                            <i class="fas fa-paper-plane"></i> Send Request
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!empty($info_requests)): ?>
                <div class="detail-card" id="info-requests">
                    <h3><i class="fas fa-question-circle"></i> Information Requests</h3>
                    <?php foreach ($info_requests as $req): ?>
                        <div style="padding: var(--spacing-md); background: var(--warning-light); border-left: 4px solid var(--warning); border-radius: var(--radius); margin-bottom: var(--spacing-md);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                                <strong>Request from <?php echo htmlspecialchars($req['username']); ?></strong>
                                <span class="badge <?php echo $req['status'] === 'pending' ? 'pending' : ($req['status'] === 'responded' ? 'resolved' : 'denied'); ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                            </div>
                            <div style="white-space: pre-wrap; margin-bottom: var(--spacing-sm);"><?php echo htmlspecialchars($req['request_message']); ?></div>
                            <?php if ($req['student_response']): ?>
                                <div style="background: var(--bg-white); padding: var(--spacing-sm); border-radius: var(--radius); margin-top: var(--spacing-sm);">
                                    <strong>Student Response:</strong>
                                    <div style="white-space: pre-wrap; margin-top: var(--spacing-xs);"><?php echo htmlspecialchars($req['student_response']); ?></div>
                                </div>
                            <?php elseif ($role === 'student' && $req['status'] === 'pending'): ?>
                                <form action="respond_to_request.php" method="post" style="margin-top: var(--spacing-md);">
                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                    <div class="form-group">
                                        <label for="response_<?php echo $req['request_id']; ?>">Your Response <span class="required">*</span></label>
                                        <textarea id="response_<?php echo $req['request_id']; ?>" name="response" rows="4" placeholder="Provide the requested information..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn-submit">
                                        <i class="fas fa-paper-plane"></i> Submit Response
                                    </button>
                                </form>
                            <?php endif; ?>
                            <small style="color: var(--text-secondary);">Requested: <?php echo date('M d, Y g:i A', strtotime($req['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($history): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-history"></i> Complaint Timeline</h3>
                    <?php foreach ($history as $item): ?>
                    <div class="history-item">
                        <div class="history-header">
                            <span class="history-action"><?php echo ucfirst(str_replace('_', ' ', $item['action'])); ?></span>
                            <span class="history-date"><?php echo date('M d, Y g:i A', strtotime($item['created_at'])); ?></span>
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">
                            <strong>Performed by:</strong> <?php echo htmlspecialchars($item['performed_by']); ?><br>
                            <?php if ($item['old_status'] && $item['new_status']): ?>
                                <strong>Status:</strong> <?php echo ucfirst($item['old_status']); ?> → <?php echo ucfirst(str_replace('_', ' ', $item['new_status'])); ?><br>
                            <?php endif; ?>
                            <?php if ($item['notes']): ?>
                                <strong>Notes:</strong> <?php echo htmlspecialchars($item['notes']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

</body>
</html>

