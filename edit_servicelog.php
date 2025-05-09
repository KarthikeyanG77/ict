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

// Get current user's name
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

// Fetch service log details
if (!isset($_GET['id'])) {
    header("Location: manage_servicelog.php");
    exit();
}

$log_id = $_GET['id'];
$log = $pdo->query("SELECT * FROM service_log WHERE service_id = $log_id")->fetch();

if (!$log) {
    header("Location: manage_servicelog.php");
    exit();
}

// Fetch assets and service centers
$assets = $pdo->query("SELECT asset_id, asset_name, r_no FROM asset ORDER BY asset_name")->fetchAll();
$centers = $pdo->query("SELECT center_id, center_name FROM service_center ORDER BY center_name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE service_log SET
            asset_id = ?,
            center_id = ?,
            service_date = ?,
            issue_description = ?,
            status = ?,
            warranty_status = ?,
            warranty_covered = ?,
            cost = ?,
            movement_ref = ?,
            diagnosis = ?,
            solution = ?
            WHERE service_id = ?");
        
        $stmt->execute([
            $_POST['asset_id'],
            $_POST['center_id'],
            $_POST['service_date'],
            $_POST['issue_description'],
            $_POST['status'],
            $_POST['warranty_status'],
            $_POST['warranty_covered'],
            $_POST['cost'],
            $_POST['movement_ref'],
            $_POST['diagnosis'],
            $_POST['solution'],
            $log_id
        ]);
        
        header("Location: manage_servicelog.php?updated=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating service log: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same styles as add_servicelog.php */
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <a href="manage_servicelog.php" class="back-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Service Log</h2>
                <div style="width: 40px;"></div>
            </div>
            
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="asset_id" class="form-label required">Asset</label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">Select Asset</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?= $asset['asset_id'] ?>" <?= $asset['asset_id'] == $log['asset_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($asset['asset_name']) ?> (R.No: <?= htmlspecialchars($asset['r_no']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="center_id" class="form-label required">Service Center</label>
                            <select class="form-select" id="center_id" name="center_id" required>
                                <option value="">Select Service Center</option>
                                <?php foreach ($centers as $center): ?>
                                    <option value="<?= $center['center_id'] ?>" <?= $center['center_id'] == $log['center_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($center['center_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="service_date" class="form-label required">Service Date</label>
                            <input type="date" class="form-control" id="service_date" name="service_date" 
                                   value="<?= htmlspecialchars($log['service_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label required">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Open" <?= $log['status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                                <option value="In Progress" <?= $log['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= $log['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Waiting for Parts" <?= $log['status'] == 'Waiting for Parts' ? 'selected' : '' ?>>Waiting for Parts</option>
                                <option value="Cancelled" <?= $log['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="warranty_status" class="form-label">Warranty Status</label>
                            <select class="form-select" id="warranty_status" name="warranty_status">
                                <option value="">Select Warranty Status</option>
                                <option value="In Warranty" <?= $log['warranty_status'] == 'In Warranty' ? 'selected' : '' ?>>In Warranty</option>
                                <option value="Out of Warranty" <?= $log['warranty_status'] == 'Out of Warranty' ? 'selected' : '' ?>>Out of Warranty</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="warranty_covered" class="form-label">Warranty Coverage</label>
                            <input type="text" class="form-control" id="warranty_covered" name="warranty_covered"
                                   value="<?= htmlspecialchars($log['warranty_covered']) ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cost" class="form-label">Cost (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="cost" name="cost"
                                   value="<?= htmlspecialchars($log['cost']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="movement_ref" class="form-label">Movement Reference</label>
                            <input type="text" class="form-control" id="movement_ref" name="movement_ref"
                                   value="<?= htmlspecialchars($log['movement_ref']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="issue_description" class="form-label required">Issue Description</label>
                        <textarea class="form-control" id="issue_description" name="issue_description" rows="3" required><?= htmlspecialchars($log['issue_description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?= htmlspecialchars($log['diagnosis']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="solution" class="form-label">Solution</label>
                        <textarea class="form-control" id="solution" name="solution" rows="3"><?= htmlspecialchars($log['solution']) ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Service Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>