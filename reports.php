<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'connect.php';

// Get filter parameters
$filter_dept = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($filter_dept > 0) {
    $where_conditions[] = "c.department_id = ?";
    $params[] = $filter_dept;
    $types .= 'i';
}

if ($filter_status && in_array($filter_status, ['pending', 'in_progress', 'resolved'])) {
    $where_conditions[] = "c.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(c.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(c.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Reports Data
// 1. Complaints by Department
$dept_query = "SELECT d.department_name, 
    COUNT(c.complaint_id) as total,
    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    AVG(CASE WHEN c.status = 'resolved' AND c.updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) ELSE NULL END) as avg_resolution_hours
    FROM departments d
    LEFT JOIN complaints c ON d.department_id = c.department_id " . 
    ($where_clause ? str_replace('c.', 'c.', $where_clause) : '') . "
    GROUP BY d.department_id, d.department_name
    ORDER BY total DESC";

// 2. Complaints by Category
$cat_query = "SELECT cat.category_name,
    COUNT(c.complaint_id) as total,
    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaint_categories cat
    LEFT JOIN complaints c ON cat.category_id = c.category_id " . 
    ($where_clause ? str_replace('c.', 'c.', $where_clause) : '') . "
    GROUP BY cat.category_id, cat.category_name
    ORDER BY total DESC";

// 3. Overall Statistics
$stats_query = "SELECT 
    COUNT(*) as total_complaints,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    AVG(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as avg_resolution_hours,
    MIN(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as min_resolution_hours,
    MAX(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as max_resolution_hours
    FROM complaints " . 
    ($where_clause ? str_replace('c.', '', $where_clause) : '');

$stats = $conn->query($stats_query)->fetch_assoc();
$dept_stats = $conn->query($dept_query)->fetch_all(MYSQLI_ASSOC);
$cat_stats = $conn->query($cat_query)->fetch_all(MYSQLI_ASSOC);

// Get departments for filter
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin</title>
    <link rel="stylesheet" href="style_adminadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-box h4 {
            margin: 0 0 0.5rem 0;
            color: #666;
            font-size: 0.9rem;
        }
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3>AdminCMS</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1>Complaint Reports & Analytics</h1>
            <div class="user-pill">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
        </header>

        <section class="content-wrapper">
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Filter Reports</h3>
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label>Department</label>
                        <select name="department_id">
                            <option value="0">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" <?php echo $filter_dept == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <h4>Total Complaints</h4>
                    <div class="number"><?php echo $stats['total_complaints']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>Pending</h4>
                    <div class="number" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>In Progress</h4>
                    <div class="number" style="color: #3b82f6;"><?php echo $stats['in_progress']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>Resolved</h4>
                    <div class="number" style="color: #10b981;"><?php echo $stats['resolved']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>Avg Resolution Time</h4>
                    <div class="number" style="color: #8b5cf6;">
                        <?php echo $stats['avg_resolution_hours'] ? round($stats['avg_resolution_hours']) . ' hrs' : 'N/A'; ?>
                    </div>
                </div>
            </div>

            <div class="card table-card">
                <h3><i class="fas fa-building"></i> Complaints by Department</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Resolved</th>
                                <th>Avg Resolution (Hours)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['department_name']); ?></strong></td>
                                    <td><?php echo $stat['total']; ?></td>
                                    <td><?php echo $stat['pending']; ?></td>
                                    <td><?php echo $stat['in_progress']; ?></td>
                                    <td><?php echo $stat['resolved']; ?></td>
                                    <td><?php echo $stat['avg_resolution_hours'] ? round($stat['avg_resolution_hours']) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card table-card">
                <h3><i class="fas fa-tags"></i> Complaints by Category</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Resolved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cat_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['category_name'] ?? 'Uncategorized'); ?></strong></td>
                                    <td><?php echo $stat['total']; ?></td>
                                    <td><?php echo $stat['pending']; ?></td>
                                    <td><?php echo $stat['in_progress']; ?></td>
                                    <td><?php echo $stat['resolved']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

</body>
</html>

