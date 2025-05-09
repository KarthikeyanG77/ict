<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Include config file
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Validate session security
if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_unset();
    session_destroy();
    header("Location: login.php?security=1");
    exit();
}

// Check for session timeout (30 minutes)
$inactive = 1800;
if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Get current user's details
$currentUserName = "User";
$user_designation = null;
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
        $user_designation = $user['designation'];
    }
    $stmt->close();
}

// Define user permissions
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
$is_lab_incharge_or_sa = in_array($user_designation, ['Lab Incharge', 'SA']);
$is_hod_or_fi = in_array($user_designation, ['HOD', 'FI']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .dashboard-card {
            height: 100%;
            transition: transform 0.3s;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            border-top-color: var(--secondary-color);
        }
        
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 8px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--accent-color);
        }
        
        .welcome-section {
            margin-bottom: 30px;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .dropdown-report, .dropdown-schedule {
            position: relative;
        }
        
        .dropdown-content, .dropdown-schedule-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 8px;
            padding: 10px 0;
            left: 0;
            right: 0;
            margin: 0 auto;
        }
        
        .dropdown-report:hover .dropdown-content,
        .dropdown-schedule:hover .dropdown-schedule-content {
            display: block;
        }
        
        .report-item, .schedule-item {
            color: var(--dark-color);
            padding: 8px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
            transition: all 0.3s;
        }
        
        .report-item:hover, .schedule-item:hover {
            background-color: var(--light-color);
            text-decoration: none;
            color: var(--primary-color);
        }
        
        .admin-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .disabled-card {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        
        .disabled-card::after {
            content: "Access Restricted";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <span><?php echo htmlspecialchars($currentUserName); ?></span>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h2>ICT IT Asset Management System</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($currentUserName); ?></p>
            <?php if ($is_admin): ?>
                <span class="badge bg-danger">Administrator</span>
            <?php elseif ($is_lab_incharge_or_sa): ?>
                <span class="badge bg-primary">Lab Incharge/SA</span>
            <?php elseif ($is_hod_or_fi): ?>
                <span class="badge bg-secondary">HOD/FI</span>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (!$is_hod_or_fi): ?>
                <!-- Employee Management -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_emp.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5>Employee Management</h5>
                            <p class="text-muted">Manage employee records</p>
                        </div>
                    </a>
                </div>

                <!-- IT Asset -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_ast.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h5>IT Asset</h5>
                            <p class="text-muted">Manage all IT assets</p>
                        </div>
                    </a>
                </div>

                <!-- Asset Type -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_astty.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <h5>Asset Type</h5>
                            <p class="text-muted">Manage asset types</p>
                        </div>
                    </a>
                </div>

                <!-- Asset Category -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_astcat.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h5>Asset Category</h5>
                            <p class="text-muted">Manage asset categories</p>
                        </div>
                    </a>
                </div>

                <!-- Service Centre -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_service.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h5>Service Centre</h5>
                            <p class="text-muted">Manage service centers</p>
                        </div>
                    </a>
                </div>

                <!-- Service Log -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_servicelog.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h5>Service Log</h5>
                            <p class="text-muted">Manage service logs</p>
                        </div>
                    </a>
                </div>

                <!-- Movement -->
                <div class="col-md-4 col-sm-6">
                    <a href="update_status.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <h5>Movement</h5>
                            <p class="text-muted">Track asset movements</p>
                        </div>
                    </a>
                </div>

                <!-- Location -->
                <div class="col-md-4 col-sm-6">
                    <a href="manage_loc.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h5>Location</h5>
                            <p class="text-muted">Location mapping</p>
                        </div>
                    </a>
                </div>

                <!-- Lab Timetable -->
                <div class="col-md-4 col-sm-6">
                    <a href="add_timetable.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <h5>Lab Timetable</h5>
                            <p class="text-muted">Manage lab timetables</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Lab Schedule (Visible to all) -->
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card text-center p-4 dropdown-schedule">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h5>Lab Schedule</h5>
                    <p class="text-muted">Lab schedules</p>
                    
                    <div class="dropdown-schedule-content">
                        <a href="request.php" class="schedule-item">
                            <i class="fas fa-calendar-plus me-2"></i> Schedule Request
                        </a>
                        <?php if ($is_admin || $is_lab_incharge_or_sa): ?>
                            <a href="approve_request.php" class="schedule-item">
                                <i class="fas fa-check-circle me-2"></i> Approve Requests
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!$is_hod_or_fi): ?>
                <!-- Reports -->
                <div class="col-md-4 col-sm-6">
                    <div class="card dashboard-card text-center p-4 dropdown-report">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h5>Reports</h5>
                        <p class="text-muted">View various reports</p>
                        
                        <div class="dropdown-content">
                            <a href="productwise_rpt.php" class="report-item">
                                <i class="fas fa-box me-2"></i> Product-wise Report
                            </a>
                            <a href="labwise_rpt.php" class="report-item">
                                <i class="fas fa-flask me-2"></i> Lab-wise Report
                            </a>
                            <a href="ser_rpt.php" class="report-item">
                                <i class="fas fa-wrench me-2"></i> Service Report
                            </a>
                            <a href="brand_rpt.php" class="report-item">
                                <i class="fas fa-tag me-2"></i> Brand-wise Report
                            </a>
                            <a href="overallasset_rpt.php" class="report-item">
                                <i class="fas fa-truck me-2"></i> Overall Asset
                            </a>
			<a href="scrap_rpt.php" class="report-item">
                                <i class="fas fa-truck me-2"></i> Scrap Report
                            </a>
                            <a href="pro_rpt.php" class="report-item">
                                <i class="fas fa-video me-2"></i> Projector Report
                            </a>
                            <a href="mvmnt_rpt.php" class="report-item">
                                <i class="fas fa-truck me-2"></i> Movement Register
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($is_hod_or_fi): ?>
                <!-- For HOD/FI users, show only the Lab Schedule card and disable others -->
                <?php 
                $disabled_cards = [
                    ['icon' => 'users', 'title' => 'Employee Management', 'desc' => 'Manage employee records'],
                    ['icon' => 'laptop', 'title' => 'IT Asset', 'desc' => 'Manage all IT assets'],
                    ['icon' => 'tags', 'title' => 'Asset Type', 'desc' => 'Manage asset types'],
                    ['icon' => 'layer-group', 'title' => 'Asset Category', 'desc' => 'Manage asset categories'],
                    ['icon' => 'tools', 'title' => 'Service Centre', 'desc' => 'Manage service centers'],
                    ['icon' => 'clipboard-list', 'title' => 'Service Log', 'desc' => 'Manage service logs'],
                    ['icon' => 'exchange-alt', 'title' => 'Movement', 'desc' => 'Track asset movements'],
                    ['icon' => 'map-marker-alt', 'title' => 'Location', 'desc' => 'Location mapping'],
                    ['icon' => 'calendar', 'title' => 'Lab Timetable', 'desc' => 'Manage lab timetables'],
                    ['icon' => 'chart-bar', 'title' => 'Reports', 'desc' => 'View various reports']
                ];
                
                foreach ($disabled_cards as $card): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-<?php echo $card['icon']; ?>"></i>
                        </div>
                        <h5><?php echo $card['title']; ?></h5>
                        <p class="text-muted"><?php echo $card['desc']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add active class to current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('a[href="' + currentPage + '"]');
            <?php
require_once 'config.php';
secure_session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's details
$currentUserName = "User";
$user_designation = null;
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
        $user_designation = $user['designation'];
    }
    $stmt->close();
}

// Define user permissions
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
$is_lab_incharge_or_sa = in_array($user_designation, ['Lab Incharge', 'SA']);
$is_hod_or_fi = in_array($user_designation, ['HOD', 'FI']);

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM service_center WHERE center_id = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['message'] = "Service center deleted successfully!";
        header("Location: manage_service.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting service center: " . $e->getMessage();
        header("Location: manage_service.php");
        exit;
    }
}

// Fetch all service centers
$stmt = $pdo->query("SELECT * FROM service_center ORDER BY center_name");
$serviceCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Service Centers | ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 8px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--accent-color);
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
            margin-bottom: 30px;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .table-responsive {
            margin-top: 20px;
        }
        
        .action-btns {
            white-space: nowrap;
        }
        
        .btn-add {
            background-color: white;
            color: var(--primary-color);
            border: none;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            background-color: var(--light-color);
            color: var(--secondary-color);
        }
        
        .back-btn {
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            transform: translateX(-3px);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .badge-admin {
            background-color: var(--accent-color);
        }
        
        .badge-lab {
            background-color: var(--primary-color);
        }
        
        .badge-hod {
            background-color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <span><?php echo htmlspecialchars($currentUserName); ?></span>
            <?php if ($is_admin): ?>
                <span class="badge badge-admin ms-2">Admin</span>
            <?php elseif ($is_lab_incharge_or_sa): ?>
                <span class="badge badge-lab ms-2">Lab Incharge/SA</span>
            <?php elseif ($is_hod_or_fi): ?>
                <span class="badge badge-hod ms-2">HOD/FI</span>
            <?php endif; ?>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container">
        <!-- Back button to return to dashboard -->
        <a href="dashboard.php" class="btn btn-secondary back-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-tools me-2"></i>Manage Service Centers</h3>
                <a href="add_service.php" class="btn btn-add">
                    <i class="fas fa-plus me-2"></i> Add New Center
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Center Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($serviceCenters) > 0): ?>
                                <?php foreach ($serviceCenters as $center): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($center['center_id']) ?></td>
                                        <td><?= htmlspecialchars($center['center_name']) ?></td>
                                        <td><?= htmlspecialchars($center['contact_person']) ?></td>
                                        <td><a href="mailto:<?= htmlspecialchars($center['contact_email']) ?>"><?= htmlspecialchars($center['contact_email']) ?></a></td>
                                        <td><a href="tel:<?= htmlspecialchars($center['phone']) ?>"><?= htmlspecialchars($center['phone']) ?></a></td>
                                        <td class="text-end action-btns">
                                            <a href="edit_service.php?id=<?= $center['center_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </a>
                                            <a href="manage_service.php?action=delete&id=<?= $center['center_id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this service center?')">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle fa-2x mb-3"></i><br>
                                        No service centers found. Please add some centers.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
            links.forEach(link => {
                link.classList.add('active');
                if (link.closest('.card')) {
                    link.closest('.card').style.borderTopColor = '#e74c3c';
                }
            });
        });
    </script>
</body>
</html>