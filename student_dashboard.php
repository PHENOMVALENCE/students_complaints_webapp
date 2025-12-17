<?php
session_start();

// Security Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'connect.php';
$message = "";

// --- Handle Form Submission (MySQLi Prepared Statement) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complaint'])) {
    $complaint = $_POST['complaint'];
    $student_username = $_SESSION['username'];
    
    // 1. Prepare
    $stmt = $conn->prepare("INSERT INTO complaints (student_username, complaint, status) VALUES (?, ?, 'pending')");
    // 2. Bind (ss = two strings)
    $stmt->bind_param("ss", $student_username, $complaint);
    
    // 3. Execute
    if ($stmt->execute()) {
        $message = "success|Complaint submitted successfully!";
    } else {
        $message = "error|Submission failed: " . $conn->error;
    }
    $stmt->close();
}

// --- Handle Delete (MySQLi Prepared Statement) ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $comp_id = $_GET['delete'];
    $user = $_SESSION['username'];

    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ? AND student_username = ?");
    $stmt->bind_param("is", $comp_id, $user); // i = integer, s = string
    
    if ($stmt->execute()) {
        $message = "success|Complaint removed.";
    }
    $stmt->close();
}

// --- Fetch Data (MySQLi Prepared Statement) ---
$student_username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT * FROM complaints WHERE student_username = ? ORDER BY complaint_id DESC");
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
                <div class="alert alert-<?php echo $type; ?>"><?php echo $text; ?></div>
            <?php endif; ?>

            <div class="grid-layout">
                <div class="card form-card">
                    <h3><i class="fas fa-pen"></i> New Complaint</h3>
                    <form action="student_dashboard.php" method="post">
                        <textarea name="complaint" placeholder="Describe your concern in detail..." required></textarea>
                        <button type="submit" class="btn-submit">Submit Record</button>
                    </form>
                </div>

                <div class="card table-card">
                    <h3><i class="fas fa-history"></i> My History</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($complaints): foreach ($complaints as $row): ?>
                                <tr>
                                    <td>#<?php echo $row['complaint_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['complaint']); ?></td>
                                    <td><span class="badge <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                                    <td>
                                        <a href="?delete=<?php echo $row['complaint_id']; ?>" class="btn-delete" onclick="return confirm('Delete this record?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="empty">No complaints recorded yet.</td></tr>
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