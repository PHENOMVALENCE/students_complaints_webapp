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

if ($filter_status && in_array($filter_status, ['pending', 'in_progress', 'resolved', 'denied'])) {
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

// Helper: run query with optional prepared params, return result or false
$run_query = function($sql, $params = [], $types = '') use ($conn) {
    if (count($params) > 0 && strlen($types) === count($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    return $conn->query($sql);
};

$stats_default = ['total_complaints' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'denied' => 0, 'avg_resolution_hours' => null, 'min_resolution_hours' => null, 'max_resolution_hours' => null];

// Reports Data
// 1. Overall Statistics (complaints table has no alias, so remove "c." from WHERE)
$stats_query = "SELECT 
    COUNT(*) as total_complaints,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied,
    AVG(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as avg_resolution_hours,
    MIN(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as min_resolution_hours,
    MAX(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as max_resolution_hours
    FROM complaints " . ($where_clause ? str_replace('c.', '', $where_clause) : '');

$stats_result = $run_query($stats_query, $params, $types);
$stats = ($stats_result && $stats_result->num_rows > 0) ? $stats_result->fetch_assoc() : $stats_default;

// 2. Complaints by Department
$dept_query = "SELECT d.department_name, 
    COUNT(c.complaint_id) as total,
    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN c.status = 'denied' THEN 1 ELSE 0 END) as denied,
    AVG(CASE WHEN c.status = 'resolved' AND c.updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) ELSE NULL END) as avg_resolution_hours
    FROM departments d
    LEFT JOIN complaints c ON d.department_id = c.department_id " . $where_clause . "
    GROUP BY d.department_id, d.department_name
    ORDER BY total DESC";

// 3. Complaints by Category
$cat_query = "SELECT cat.category_name,
    COUNT(c.complaint_id) as total,
    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN c.status = 'denied' THEN 1 ELSE 0 END) as denied
    FROM complaint_categories cat
    LEFT JOIN complaints c ON cat.category_id = c.category_id " . ($where_clause ? " " . $where_clause : "") . "
    GROUP BY cat.category_id, cat.category_name
    ORDER BY total DESC";

$dept_result = $run_query($dept_query, $params, $types);
$cat_result = $run_query($cat_query, $params, $types);
$dept_stats = $dept_result ? $dept_result->fetch_all(MYSQLI_ASSOC) : [];
$cat_stats = $cat_result ? $cat_result->fetch_all(MYSQLI_ASSOC) : [];

// Get departments for filter
$dept_list_result = $conn->query("SELECT * FROM departments ORDER BY department_name");
$departments = $dept_list_result ? $dept_list_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | Admin</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-card {
            background: var(--bg-white);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: var(--spacing-xl);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: var(--spacing-sm);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        .filter-group select,
        .filter-group input {
            padding: var(--spacing-md);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .stat-box {
            background: var(--bg-white);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-box h4 {
            margin: 0 0 var(--spacing-sm) 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-box .number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .stat-box.pending .number { color: var(--warning); }
        .stat-box.in-progress .number { color: var(--info); }
        .stat-box.resolved .number { color: var(--success); }
        .stat-box.denied .number { color: var(--danger); }
        
        .chart-placeholder {
            background: var(--bg-light);
            padding: var(--spacing-xl);
            border-radius: var(--radius);
            text-align: center;
            color: var(--text-secondary);
            margin: var(--spacing-lg) 0;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-shield-alt"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="teacher_approval.php"><i class="fas fa-user-check"></i> Teacher Requests</a>
            <a href="students_complaints.php"><i class="fas fa-exclamation-circle"></i> Student Complaints</a>
            <a href="users_management.php"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="manage_departments.php"><i class="fas fa-building"></i> Departments</a>
            <a href="manage_categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
            <div class="nav-divider"></div>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <div class="admin-profile">
                <i class="fas fa-user-shield"></i>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <section class="content-wrapper">
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Filter Reports</h3>
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Department</label>
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
                        <label><i class="fas fa-tag"></i> Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="denied" <?php echo $filter_status === 'denied' ? 'selected' : ''; ?>>Denied</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="padding: var(--spacing-md);">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="reports.php" class="btn btn-secondary" style="text-decoration: none; text-align: center; padding: var(--spacing-md); margin-top: var(--spacing-sm);">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <h4><i class="fas fa-file-invoice"></i> Total Complaints</h4>
                    <div class="number"><?php echo $stats['total_complaints'] ?? 0; ?></div>
                </div>
                <div class="stat-box pending">
                    <h4><i class="fas fa-clock"></i> Pending</h4>
                    <div class="number"><?php echo $stats['pending'] ?? 0; ?></div>
                </div>
                <div class="stat-box in-progress">
                    <h4><i class="fas fa-spinner"></i> In Progress</h4>
                    <div class="number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                </div>
                <div class="stat-box resolved">
                    <h4><i class="fas fa-check-circle"></i> Resolved</h4>
                    <div class="number"><?php echo $stats['resolved'] ?? 0; ?></div>
                </div>
                <div class="stat-box denied">
                    <h4><i class="fas fa-times-circle"></i> Denied</h4>
                    <div class="number"><?php echo $stats['denied'] ?? 0; ?></div>
                </div>
                <div class="stat-box">
                    <h4><i class="fas fa-hourglass-half"></i> Avg Resolution</h4>
                    <div class="number" style="font-size: 1.75rem;">
                        <?php echo $stats['avg_resolution_hours'] ? round($stats['avg_resolution_hours']) . ' hrs' : 'N/A'; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-building"></i> Complaints by Department</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Resolved</th>
                                <th>Denied</th>
                                <th>Avg Resolution (Hours)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($dept_stats): foreach ($dept_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['department_name']); ?></strong></td>
                                    <td><strong><?php echo $stat['total']; ?></strong></td>
                                    <td><span class="badge pending"><?php echo $stat['pending']; ?></span></td>
                                    <td><span class="badge in-progress"><?php echo $stat['in_progress']; ?></span></td>
                                    <td><span class="badge resolved"><?php echo $stat['resolved']; ?></span></td>
                                    <td><span class="badge denied"><?php echo $stat['denied']; ?></span></td>
                                    <td><?php echo $stat['avg_resolution_hours'] ? round($stat['avg_resolution_hours']) . ' hrs' : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="empty">No department data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-tags"></i> Complaints by Category</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Resolved</th>
                                <th>Denied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($cat_stats): foreach ($cat_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['category_name'] ?? 'Uncategorized'); ?></strong></td>
                                    <td><strong><?php echo $stat['total']; ?></strong></td>
                                    <td><span class="badge pending"><?php echo $stat['pending']; ?></span></td>
                                    <td><span class="badge in-progress"><?php echo $stat['in_progress']; ?></span></td>
                                    <td><span class="badge resolved"><?php echo $stat['resolved']; ?></span></td>
                                    <td><span class="badge denied"><?php echo $stat['denied']; ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="empty">No category data available.</td></tr>
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
