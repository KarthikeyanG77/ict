<?php
require_once 'config.php';
secure_session_start();
redirect_if_not_logged_in();

// Get current user's details for display
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

// Fetch all asset types for the dropdown
$assetTypes = $pdo->query("SELECT type_id, type_name FROM asset_type ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);

// Initialize variables
$selectedAssetType = '';
$reportData = [];
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $selectedAssetType = $_POST['type_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.asset_id,
                a.asset_name,
                a.serial_no,
                a.r_no,
                a.brand,
                a.status,
                at.type_name,
                COUNT(sl.service_id) as service_count,
                MAX(sl.service_date) as last_service_date
            FROM 
                asset a
            JOIN 
                asset_type at ON a.type_id = at.type_id
            LEFT JOIN 
                service_log sl ON a.asset_id = sl.asset_id
            WHERE 
                a.type_id = ?
            GROUP BY 
                a.asset_id
            ORDER BY 
                a.asset_name
        ");
        $stmt->execute([$selectedAssetType]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($reportData)) {
            $error = "No assets found for the selected product type.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product-wise Asset Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1400px; margin-top: 30px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .report-header { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .table-responsive { margin-top: 20px; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-service { background-color: #fff3cd; color: #856404; }
        .status-scrapped { background-color: #f8d7da; color: #721c24; }
        .clickable-row { cursor: pointer; }
        .clickable-row:hover { background-color: #f8f9fa; }
        .action-btns { white-space: nowrap; }
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
            background-color: #3498db;
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
            color: #e74c3c;
        }
        .back-btn {
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .back-btn:hover {
            transform: translateX(-3px);
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
            <span><?= htmlspecialchars($currentUserName) ?></span>
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
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="fas fa-chart-pie"></i> Product-wise Asset Report</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="type_id" class="form-label">Select Product Type</label>
                            <select class="form-select" id="type_id" name="type_id" required>
                                <option value="">-- Select Product Type --</option>
                                <?php foreach ($assetTypes as $type): ?>
                                    <option value="<?= $type['type_id'] ?>" 
                                        <?= $selectedAssetType == $type['type_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="generate_report" class="btn btn-primary w-100">
                                <i class="fas fa-chart-bar me-2"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($reportData)): ?>
                    <div class="report-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">
                                    <i class="fas fa-file-alt me-2"></i> 
                                    Asset Report for: <?= htmlspecialchars($reportData[0]['type_name']) ?>
                                </h4>
                                <div class="text-muted">
                                    <span class="me-3"><i class="fas fa-calendar me-1"></i> <?= date('F j, Y') ?></span>
                                    <span><i class="fas fa-cubes me-1"></i> Total Assets: <?= count($reportData) ?></span>
                                </div>
                            </div>
                            <div class="d-flex">
                                <button class="btn btn-sm btn-success me-2" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                                <a href="export_asset_report.php?type_id=<?= $selectedAssetType ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-file-excel me-1"></i> Export
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Asset Name</th>
                                    <th>Serial No</th>
                                    <th>R No</th>
                                    <th>Brand</th>
                                    <th>Status</th>
                                    <th>Services</th>
                                    <th>Last Service</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $index => $row): ?>
                                    <tr class="clickable-row" onclick="window.location='asset_details.php?id=<?= $row['asset_id'] ?>'">
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($row['asset_name']) ?></td>
                                        <td><?= $row['serial_no'] !== null ? htmlspecialchars($row['serial_no']) : 'N/A' ?></td>
                                        <td><?= $row['r_no'] !== null ? htmlspecialchars($row['r_no']) : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($row['brand']) ?></td>
                                        <td>
                                            <?php if ($row['status'] == 'active'): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php elseif ($row['status'] == 'service'): ?>
                                                <span class="status-badge status-service">In Service</span>
                                            <?php else: ?>
                                                <span class="status-badge status-scrapped">Scrapped</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($row['service_count']) ?></td>
                                        <td><?= $row['last_service_date'] ? date('M j, Y', strtotime($row['last_service_date'])) : 'Never' ?></td>
                                        <td class="action-btns">
                                            <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-primary me-1" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="service_log.php?asset_id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-warning" title="Service History">
                                                <i class="fas fa-tools"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <div class="text-muted">
                            Report generated at: <?= date('h:i A') ?>
                        </div>
                        <div>
                            <button class="btn btn-success me-2" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print Report
                            </button>
                            <a href="export_asset_report.php?type_id=<?= $selectedAssetType ?>" class="btn btn-info">
                                <i class="fas fa-file-excel me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make rows clickable but exclude actions column
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.clickable-row');
            rows.forEach(row => {
                const cells = Array.from(row.cells).slice(0, -1); // Exclude last cell (actions)
                
                cells.forEach(cell => {
                    cell.addEventListener('click', function() {
                        const link = row.getAttribute('onclick').replace("window.location='", "").replace("'", "");
                        window.location = link;
                    });
                });
            });
        });
    </script>
</body>
</html>