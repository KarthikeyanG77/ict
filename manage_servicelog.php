<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config file and start session
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name from database
$currentUserName = "User";
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
    }
    $stmt->close();
}

// Query to get service assets with additional information
try {
    $stmt = $pdo->query("
        SELECT 
            a.asset_id,
            a.asset_name,
            a.r_no,
            a.serial_no,
            l.location_name,
            curr.emp_name as `current_user`,
            prev.emp_name as `previous_user`
        FROM asset a
        JOIN location l ON a.location_id = l.location_id
        LEFT JOIN employee curr ON a.current_holder = curr.emp_id
        LEFT JOIN employee prev ON a.previous_holder = prev.emp_id
        WHERE a.status = 'service'
        ORDER BY a.asset_name
        LIMIT 50
    ");
    $service_assets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching service assets: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets in Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container {
            max-width: 1400px;
        }
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: #0d6efd;
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
        }
        .logout-btn:hover {
            color: #0d6efd;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            padding: 15px 20px;
        }
        .back-btn {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .back-btn:hover {
            color: white;
            background-color: rgba(255,255,255,0.2);
        }
        .add-btn {
            background-color: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .add-btn:hover {
            background-color: #218838;
            color: white;
        }
        .table-responsive {
            margin-top: 20px;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #343a40;
            color: white;
            vertical-align: middle;
        }
        .table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        .service-row {
            background-color: #fff3cd;
        }
        .action-btns {
            white-space: nowrap;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-service {
            background-color: #fff3cd;
            color: #856404;
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

    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
                <h2 class="mb-0 text-center"><i class="fas fa-tools me-2"></i>Assets in Service</h2>
                <a href="add_servicelog.php" class="add-btn">
                    <i class="fas fa-plus me-1"></i> Add New
                </a>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Asset Name</th>
                                <th>Serial No</th>
                                <th>R.No</th>
                                <th>Location</th>
                                <th>Current User</th>
                                <th>Previous User</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($service_assets as $index => $asset): ?>
                                <tr class="service-row">
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td><?= htmlspecialchars($asset['serial_no'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($asset['r_no']) ?></td>
                                    <td><?= htmlspecialchars($asset['location_name']) ?></td>
                                    <td><?= htmlspecialchars($asset['current_user'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($asset['previous_user'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge status-service">In Service</span>
                                    </td>
                                    <td class="action-btns">
                                        <a href="edit_servicelog.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="del_servicelog.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this service record?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($service_assets)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No assets currently in service.
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
        // Confirm before deleting
        document.querySelectorAll('.btn-danger').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this record?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>