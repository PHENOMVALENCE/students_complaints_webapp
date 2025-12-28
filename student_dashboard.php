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

// --- Handle Form Submission (MySQLi Prepared Statement) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complaint'])) {
    $title = trim($_POST['title'] ?? '');
    $complaint = trim($_POST['complaint']);
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $student_username = $_SESSION['username'];
    
    // Validation
    if (empty($title)) {
        $message = "error|Please provide a complaint title.";
    } elseif (empty($complaint)) {
        $message = "error|Please describe your complaint.";
    } elseif (!$department_id) {
        $message = "error|Please select a department.";
    } else {
        // Prepare insert statement with new fields
        $stmt = $conn->prepare("INSERT INTO complaints (student_username, title, complaint, category_id, department_id, status, routed_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("sssii", $student_username, $title, $complaint, $category_id, $department_id);
        
        if ($stmt->execute()) {
            $complaint_id = $conn->insert_id;
            
            // Log the complaint creation in history
            $history_stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, action, performed_by, new_status, notes) VALUES (?, 'submitted', ?, 'pending', 'Complaint submitted and routed to department')");
            $history_stmt->bind_param("is", $complaint_id, $student_username);
            $history_stmt->execute();
            $history_stmt->close();
            
            $_SESSION['message'] = "success|Complaint #{$complaint_id} submitted successfully and routed to department!";
            header("Location: student_dashboard.php");
            exit;
        } else {
            $message = "error|Submission failed: " . $conn->error;
        }
        $stmt->close();
    }
}

// --- Handle Delete (MySQLi Prepared Statement) ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $comp_id = $_GET['delete'];
    $user = $_SESSION['username'];

    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ? AND student_username = ?");
    $stmt->bind_param("is", $comp_id, $user); // i = integer, s = string
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Complaint removed successfully.";
        header("Location: student_dashboard.php");
        exit;
    } else {
        $message = "error|Failed to delete complaint.";
    }
    $stmt->close();
}

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- Fetch Data with Joins (MySQLi Prepared Statement) ---
$student_username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT c.*, d.department_name, cat.category_name 
                        FROM complaints c 
                        LEFT JOIN departments d ON c.department_id = d.department_id 
                        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id 
                        WHERE c.student_username = ? 
                        ORDER BY c.complaint_id DESC");
$stmt->bind_param("s", $student_username);
$stmt->execute();
$result = $stmt->get_result();
$complaints = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style_dassh.css">
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
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-hint {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #718096;
        }
        .required {
            color: #e53e3e;
        }
        table tbody tr {
            transition: background-color 0.2s;
        }
        table tbody tr:hover {
            background-color: #f7fafc;
        }
        .btn-view, .btn-delete {
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-view:hover {
            background: #edf2f7;
            transform: scale(1.1);
        }
        .btn-delete:hover {
            background: #fed7d7;
            transform: scale(1.1);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3>CMS Pro</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Student Portal</h1>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
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

            <div class="grid-layout">
                <div class="card form-card">
                    <h3><i class="fas fa-pen"></i> New Complaint</h3>
                    <form action="student_dashboard.php" method="post">
                        <div class="form-group">
                            <label for="title">Complaint Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" placeholder="Brief summary of your complaint" required maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id">
                                <option value="">Select a category (optional)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="department_id">Target Department <span class="required">*</span></label>
                            <select id="department_id" name="department_id" required>
                                <option value="">Select a department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Your complaint will be automatically routed to this department</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="complaint">Complaint Description <span class="required">*</span></label>
                            <textarea id="complaint" name="complaint" placeholder="Describe your concern in detail..." required rows="6"></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Complaint
                        </button>
                    </form>
                </div>

                <div class="card table-card">
                    <h3><i class="fas fa-history"></i> My History</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Complaint ID</th>
                                    <th>Title</th>
                                    <th>Department</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($complaints): foreach ($complaints as $row): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['complaint_id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['title'] ?? 'No title'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($row['complaint'], 0, 80)) . (strlen($row['complaint']) > 80 ? '...' : ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower(str_replace('_', '-', $row['status'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($row['status'] === 'pending'): ?>
                                        <a href="?delete=<?php echo $row['complaint_id']; ?>" class="btn-delete" onclick="return confirm('Delete this complaint?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" class="empty">No complaints recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

</body>
</html>