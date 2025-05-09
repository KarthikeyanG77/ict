<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if ID parameter exists
if (!isset($_GET['id'])) {
    header("Location: manage_servicelog.php");
    exit();
}

$log_id = $_GET['id'];

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM service_log WHERE service_id = ?");
        $stmt->execute([$log_id]);
        
        header("Location: manage_servicelog.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        header("Location: manage_servicelog.php?deleted=0");
        exit();
    }
}

// Get service log details for confirmation
$log = $pdo->query("SELECT sl.*, a.asset_name, sc.center_name 
                   FROM service_log sl
                   JOIN asset a ON sl.asset_id = a.asset_id
                   JOIN service_center sc ON sl.center_id = sc.center_id
                   WHERE sl.service_id = $log_id")->fetch();

if (!$log) {
    header("Location: manage_servicelog.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Service Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card-header {
            background-color: #dc3545;
            color: white;
        }
        .back-btn {
            color: white;
            text-decoration: none;
        }
        .confirmation-box {
            border-left: 4px solid #dc3545;
            padding-left: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <a href="manage_servicelog.php" class="back-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h2 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Delete Service Log</h2>
                <div style="width: 40px;"></div>
            </div>
            
            <div class="card-body">
                <div class="confirmation-box">
                    <h4>Are you sure you want to delete this service log?</h4>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                
                <div class="mb-4">
                    <h5>Service Log Details</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Asset</th>
                            <td><?= htmlspecialchars($log['asset_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Service Center</th>
                            <td><?= htmlspecialchars($log['center_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Service Date</th>
                            <td><?= date('d M Y', strtotime($log['service_date'])) ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><?= htmlspecialchars($log['status']) ?></td>
                        </tr>
                    </table>
                </div>
                
                <form method="POST">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_servicelog.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Confirm Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>