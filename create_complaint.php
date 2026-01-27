<?php
session_start();

// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'connect.php';
$message = "";

// Fetch departments and categories for dropdowns
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT * FROM complaint_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complaint'])) {
    $title = trim($_POST['title'] ?? '');
    $complaint = trim($_POST['complaint']);
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $student_username = $_SESSION['username'];
    
    // Validation
    if (empty($title)) {
        $message = "error|Please provide a complaint title.";
    } elseif (empty($complaint)) {
        $message = "error|Please describe your complaint.";
    } elseif (!$department_id) {
        $message = "error|Please select a department.";
    } else {
        // Prepare insert statement (include is_anonymous)
        $stmt = $conn->prepare("INSERT INTO complaints (student_username, title, complaint, category_id, department_id, status, routed_at, is_anonymous) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?)");
        $stmt->bind_param("sssiii", $student_username, $title, $complaint, $category_id, $department_id, $is_anonymous);
        
        if ($stmt->execute()) {
            $complaint_id = $conn->insert_id;
            
            // Handle file uploads
            $upload_dir = "uploads/complaints/{$complaint_id}/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!empty($_FILES['attachments']['name'][0])) {
                $file_count = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_name = $_FILES['attachments']['name'][$i];
                        $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        $file_type = $_FILES['attachments']['type'][$i];
                        
                        // Validate file type
                        if (!in_array($file_type, $allowed_types)) {
                            continue; // Skip invalid files
                        }
                        
                        // Validate file size
                        if ($file_size > $max_size) {
                            continue; // Skip oversized files
                        }
                        
                        // Generate unique filename
                        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Store attachment in database
                            $attach_stmt = $conn->prepare("INSERT INTO complaint_attachments (complaint_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $attach_stmt->bind_param("isssi", $complaint_id, $file_name, $file_path, $file_type, $file_size);
                            $attach_stmt->execute();
                            $attach_stmt->close();
                        }
                    }
                }
            }
            
            // Log the complaint creation in history
            $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, new_status, notes) VALUES (?, 'submitted', ?, 'pending', ?)");
            $history_note = $is_anonymous ? 'Complaint submitted anonymously and routed to department' : 'Complaint submitted and routed to department';
            $history_stmt->bind_param("iss", $complaint_id, $student_username, $history_note);
            $history_stmt->execute();
            $history_stmt->close();
            
            $_SESSION['message'] = "success|Complaint #{$complaint_id} submitted successfully and routed to department!{$upload_info}";
            header("Location: track_complaints.php");
            exit;
        } else {
            $message = "error|Submission failed: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - Student Portal</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-lg);
            border-bottom: 2px solid var(--border);
        }
        
        .form-header h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .form-header p {
            color: var(--text-secondary);
        }
        
        .form-section {
            background: var(--bg-white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-lg);
        }
        
        .form-section h3 {
            color: var(--primary);
            margin-bottom: var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .help-text {
            background: var(--info-light);
            border-left: 4px solid var(--info);
            padding: var(--spacing-md);
            border-radius: var(--radius);
            margin-bottom: var(--spacing-lg);
            color: #1e40af;
        }
        
        .help-text i {
            margin-right: var(--spacing-sm);
        }
        
        .char-count {
            text-align: right;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: var(--spacing-xs);
        }
        
        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
        }
        
        .btn-cancel {
            background: var(--text-secondary);
            color: white;
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: var(--transition);
        }
        
        .btn-cancel:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-graduation-cap"></i> Student Portal</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="create_complaint.php" class="active"><i class="fas fa-plus-circle"></i> Submit Complaint</a>
            <a href="track_complaints.php"><i class="fas fa-list-alt"></i> Track Complaints</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-file-alt"></i> Submit New Complaint</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
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

            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-paper-plane"></i> File a New Complaint</h2>
                    <p>Fill out the form below to submit your complaint. Make sure to provide clear and detailed information.</p>
                </div>

                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    <strong>Tip:</strong> Be specific and provide as much detail as possible. This will help us resolve your complaint faster.
                </div>

                <form action="create_complaint.php" method="post">
                    <div class="form-section">
                        <h3><i class="fas fa-heading"></i> Basic Information</h3>
                        
                        <div class="form-group">
                            <label for="title">Complaint Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" placeholder="e.g., Issue with Library Services" required maxlength="200" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            <div class="char-count"><span id="titleCount">0</span>/200 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id"><i class="fas fa-tag"></i> Category</label>
                            <select id="category_id" name="category_id">
                                <option value="">Select a category (optional)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Categorizing helps route your complaint to the right department</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="department_id"><i class="fas fa-building"></i> Target Department <span class="required">*</span></label>
                            <select id="department_id" name="department_id" required>
                                <option value="">Select a department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Your complaint will be automatically routed to this department for review</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-align-left"></i> Complaint Details</h3>
                        
                        <div class="form-group">
                            <label for="complaint">Complaint Description <span class="required">*</span></label>
                            <textarea id="complaint" name="complaint" placeholder="Please describe your complaint in detail. Include relevant dates, times, locations, and any other information that might help us understand and resolve your issue..." required rows="10"><?php echo isset($_POST['complaint']) ? htmlspecialchars($_POST['complaint']) : ''; ?></textarea>
                            <small class="form-hint">Provide a detailed description of your complaint. The more information you provide, the better we can help you.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-paperclip"></i> Evidence Attachments</h3>
                        
                        <div class="form-group">
                            <label for="attachments"><i class="fas fa-file-upload"></i> Supporting Documents</label>
                            <input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif">
                            <small class="form-hint">
                                <i class="fas fa-info-circle"></i> You can upload multiple files (PDF or images). Maximum file size: 5MB per file. Accepted formats: PDF, JPG, PNG, GIF.
                            </small>
                            <div id="fileList" style="margin-top: var(--spacing-md);"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-user-secret"></i> Privacy Options</h3>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer;">
                                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1" style="width: auto;">
                                <span><i class="fas fa-eye-slash"></i> Submit this complaint anonymously</span>
                            </label>
                            <small class="form-hint">
                                <i class="fas fa-shield-alt"></i> When enabled, your identity will be hidden from department staff. Administrators can still view your identity for system management purposes.
                            </small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit" style="flex: 1;">
                            <i class="fas fa-paper-plane"></i> Submit Complaint
                        </button>
                        <a href="student_dashboard.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>

<script>
    // Character counter for title
    const titleInput = document.getElementById('title');
    const titleCount = document.getElementById('titleCount');
    
    if (titleInput && titleCount) {
        titleInput.addEventListener('input', function() {
            titleCount.textContent = this.value.length;
        });
        titleCount.textContent = titleInput.value.length;
    }

    // File upload preview
    const fileInput = document.getElementById('attachments');
    const fileList = document.getElementById('fileList');
    
    if (fileInput && fileList) {
        fileInput.addEventListener('change', function() {
            fileList.innerHTML = '';
            const files = this.files;
            
            if (files.length > 0) {
                const list = document.createElement('ul');
                list.style.listStyle = 'none';
                list.style.padding = '0';
                list.style.margin = '0';
                
                Array.from(files).forEach((file, index) => {
                    const li = document.createElement('li');
                    li.style.padding = 'var(--spacing-sm)';
                    li.style.background = 'var(--bg-light)';
                    li.style.borderRadius = 'var(--radius)';
                    li.style.marginBottom = 'var(--spacing-xs)';
                    li.style.display = 'flex';
                    li.style.alignItems = 'center';
                    li.style.gap = 'var(--spacing-sm)';
                    
                    const icon = document.createElement('i');
                    if (file.type === 'application/pdf') {
                        icon.className = 'fas fa-file-pdf';
                        icon.style.color = '#dc2626';
                    } else if (file.type.startsWith('image/')) {
                        icon.className = 'fas fa-file-image';
                        icon.style.color = '#10b981';
                    } else {
                        icon.className = 'fas fa-file';
                        icon.style.color = '#6b7280';
                    }
                    
                    const name = document.createElement('span');
                    name.textContent = file.name;
                    name.style.flex = '1';
                    
                    const size = document.createElement('span');
                    size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                    size.style.color = 'var(--text-secondary)';
                    size.style.fontSize = '0.875rem';
                    
                    li.appendChild(icon);
                    li.appendChild(name);
                    li.appendChild(size);
                    list.appendChild(li);
                });
                
                fileList.appendChild(list);
            }
        });
    }
</script>

</body>
</html>
