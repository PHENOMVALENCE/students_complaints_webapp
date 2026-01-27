<?php
session_start();

// Security Check - All logged in users can access
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

require 'connect.php';

$message = "";
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch current user data
$user_stmt = $conn->prepare("SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON u.department_id = d.department_id WHERE u.username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data) {
    header("Location: index.php");
    exit;
}

// Handle username update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_username'])) {
    $new_username = trim($_POST['new_username']);
    
    if (empty($new_username)) {
        $message = "error|Username cannot be empty.";
    } elseif ($new_username === $username) {
        $message = "error|New username is the same as current username.";
    } else {
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND username != ?");
        $check_stmt->bind_param("ss", $new_username, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "error|Username already exists. Please choose a different username.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Update username in users table
            $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
            $update_stmt->bind_param("ss", $new_username, $username);
            
            if ($update_stmt->execute()) {
                // Update username in complaints table (if student)
                if ($role === 'student') {
                    $update_complaints = $conn->prepare("UPDATE complaints SET student_username = ? WHERE student_username = ?");
                    $update_complaints->bind_param("ss", $new_username, $username);
                    $update_complaints->execute();
                    $update_complaints->close();
                }
                
                // Update session
                $_SESSION['username'] = $new_username;
                $username = $new_username;
                
                $_SESSION['message'] = "success|Username updated successfully!";
                header("Location: profile.php");
                exit;
            } else {
                $message = "error|Failed to update username: " . $conn->error;
            }
            $update_stmt->close();
        }
    }
}

// Handle password update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "error|All password fields are required.";
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $message = "error|Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $message = "error|New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "error|Password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $update_stmt->bind_param("ss", $hashed_password, $username);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "success|Password updated successfully!";
            header("Location: profile.php");
            exit;
        } else {
            $message = "error|Failed to update password: " . $conn->error;
        }
        $update_stmt->close();
    }
}

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Refresh user data after updates
$user_stmt = $conn->prepare("SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON u.department_id = d.department_id WHERE u.username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Get dashboard URL based on role
$dashboard_url = $role . "_dashboard.php";
if ($role === 'department_officer') {
    $dashboard_url = 'department_officer_dashboard.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo ucfirst($role); ?> Portal</title>
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            text-align: center;
        }
        
        .profile-header .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto var(--spacing-md);
            border: 4px solid white;
        }
        
        .profile-header h2 {
            color: white;
            margin-bottom: var(--spacing-xs);
        }
        
        .profile-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }
        
        .profile-section {
            background: var(--bg-white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-lg);
        }
        
        .profile-section h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .info-item {
            padding: var(--spacing-md);
            background: var(--bg-light);
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
        }
        
        .info-item label {
            display: block;
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--spacing-xs);
            font-weight: 600;
        }
        
        .info-item .value {
            font-size: 1.1rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: var(--spacing-md);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: var(--spacing-xs);
            transition: var(--transition);
        }
        
        .password-toggle-btn:hover {
            color: var(--primary);
        }
        
        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3>
                <?php if ($role === 'student'): ?>
                    <i class="fas fa-graduation-cap"></i> Student Portal
                <?php elseif ($role === 'teacher'): ?>
                    <i class="fas fa-chalkboard-teacher"></i> Teacher Portal
                <?php elseif ($role === 'department_officer'): ?>
                    <i class="fas fa-building"></i> Department Portal
                <?php else: ?>
                    <i class="fas fa-shield-alt"></i> Admin Panel
                <?php endif; ?>
            </h3>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo $dashboard_url; ?>"><i class="fas fa-home"></i> Dashboard</a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
            <?php if ($role === 'student'): ?>
                <a href="create_complaint.php"><i class="fas fa-plus-circle"></i> Submit Complaint</a>
                <a href="track_complaints.php"><i class="fas fa-list-alt"></i> Track Complaints</a>
            <?php elseif ($role === 'teacher'): ?>
                <a href="teacher_dashboard.php#complaints"><i class="fas fa-list-alt"></i> All Complaints</a>
            <?php elseif ($role === 'department_officer'): ?>
                <a href="department_officer_dashboard.php#complaints"><i class="fas fa-list-alt"></i> My Complaints</a>
            <?php elseif ($role === 'admin'): ?>
                <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
                <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <?php endif; ?>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($username); ?></span>
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

            <div class="profile-header">
                <div class="avatar">
                    <?php if ($role === 'student'): ?>
                        <i class="fas fa-user-graduate"></i>
                    <?php elseif ($role === 'teacher'): ?>
                        <i class="fas fa-chalkboard-teacher"></i>
                    <?php elseif ($role === 'department_officer'): ?>
                        <i class="fas fa-user-tie"></i>
                    <?php else: ?>
                        <i class="fas fa-user-shield"></i>
                    <?php endif; ?>
                </div>
                <h2><?php echo htmlspecialchars($username); ?></h2>
                <p>
                    <span class="role-badge <?php echo strtolower(str_replace('_', '-', $role)); ?>" style="background: rgba(255,255,255,0.2); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.9rem;">
                        <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                    </span>
                </p>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <label><i class="fas fa-id-badge"></i> User ID</label>
                    <div class="value">#<?php echo $user_data['user_id']; ?></div>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-user"></i> Username</label>
                    <div class="value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-user-tag"></i> Role</label>
                    <div class="value">
                        <span class="role-badge <?php echo strtolower(str_replace('_', '-', $user_data['role'])); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $user_data['role'])); ?>
                        </span>
                    </div>
                </div>
                <?php if ($user_data['department_name']): ?>
                <div class="info-item">
                    <label><i class="fas fa-building"></i> Department</label>
                    <div class="value"><?php echo htmlspecialchars($user_data['department_name']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($role === 'teacher'): ?>
                <div class="info-item">
                    <label><i class="fas fa-check-circle"></i> Approval Status</label>
                    <div class="value">
                        <span class="approval-badge <?php echo $user_data['approved'] ? 'approved' : 'pending'; ?>" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                            <i class="fas <?php echo $user_data['approved'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                            <?php echo $user_data['approved'] ? 'Approved' : 'Pending Approval'; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-edit"></i> Change Username</h3>
                <form method="post" action="profile.php">
                    <div class="form-group">
                        <label for="new_username"><i class="fas fa-user"></i> New Username <span class="required">*</span></label>
                        <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required maxlength="50" placeholder="Enter new username">
                        <small class="form-hint">Your username must be unique. This will update your login credentials.</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_username" class="btn-submit" style="width: auto; padding: var(--spacing-md) var(--spacing-xl);">
                            <i class="fas fa-save"></i> Update Username
                        </button>
                    </div>
                </form>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="post" action="profile.php" id="passwordForm">
                    <div class="form-group">
                        <label for="current_password"><i class="fas fa-key"></i> Current Password <span class="required">*</span></label>
                        <div class="password-toggle">
                            <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-lock"></i> New Password <span class="required">*</span></label>
                        <div class="password-toggle">
                            <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Enter new password (min. 6 characters)">
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-hint">Password must be at least 6 characters long.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password <span class="required">*</span></label>
                        <div class="password-toggle">
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Confirm your new password">
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_password" class="btn-submit" style="width: auto; padding: var(--spacing-md) var(--spacing-xl);">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>

            <div class="card" style="background: var(--info-light); border-left: 4px solid var(--info);">
                <h3 style="color: #1e40af;"><i class="fas fa-info-circle"></i> Account Information</h3>
                <p style="color: #1e40af; margin: 0;">
                    <strong>Note:</strong> Your role and department assignments can only be changed by an administrator. 
                    If you need to update these, please contact your system administrator.
                </p>
            </div>
        </section>
    </main>
</div>

<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password confirmation validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirmation do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});
</script>

</body>
</html>
