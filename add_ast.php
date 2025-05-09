<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name and designation
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

// Check admin privileges
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
if (!$is_admin) {
    $_SESSION['error_message'] = "You don't have permission to access this page";
    header("Location: manage_ast.php");
    exit();
}

// Fetch asset types
$asset_types = [];
$type_query = "SELECT type_id, type_name FROM asset_type ORDER BY type_name";
$type_result = $conn->query($type_query);
if ($type_result) {
    $asset_types = $type_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch locations
$locations = [];
$location_query = "SELECT location_id, location_name FROM location ORDER BY location_name";
$location_result = $conn->query($location_query);
if ($location_result) {
    $locations = $location_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch employees for current_holder dropdown
$employees = [];
$employee_query = "SELECT emp_id, emp_name FROM employee ORDER BY emp_name";
$employee_result = $conn->query($employee_query);
if ($employee_result) {
    $employees = $employee_result->fetch_all(MYSQLI_ASSOC);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    // Start transaction
    $conn->begin_transaction();

    try {
        // Basic information
        $asset_name = trim($_POST['asset_name']);
        $serial_no = !empty(trim($_POST['serial_no'] ?? '')) ? trim($_POST['serial_no']) : null;
        $r_no = !empty(trim($_POST['r_no'] ?? '')) ? trim($_POST['r_no']) : null;
        $model = trim($_POST['model'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $type_id = (int)$_POST['type_id'];
        $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        
        // Handle current_holder and previous_holder as text (not integers)
        $current_holder = !empty($_POST['current_holder']) ? trim($_POST['current_holder']) : null;
        $previous_holder = !empty($_POST['previous_holder']) ? trim($_POST['previous_holder']) : null;
        
        // Handle dates
        $purchase_date = !empty($_POST['purchase_date']) ? date('Y-m-d', strtotime($_POST['purchase_date'])) : null;
        $warranty_expiry = !empty($_POST['warranty_expiry']) ? date('Y-m-d', strtotime($_POST['warranty_expiry'])) : null;
        
        $status = in_array($_POST['status'] ?? '', ['active', 'service', 'scrapped']) ? $_POST['status'] : 'active';
        $lan_status = in_array($_POST['lan_status'] ?? '', ['Working', 'Not working']) ? $_POST['lan_status'] : null;

        // Validate required fields
        if (empty($asset_name)) {
            throw new Exception("Asset name is required");
        }
        if (empty($type_id)) {
            throw new Exception("Asset type is required");
        }

        // Check for duplicate serial_no if provided
        if ($serial_no !== null) {
            $check_query = "SELECT asset_id FROM asset WHERE serial_no = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $serial_no);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception("An asset with this serial number already exists");
            }
            $check_stmt->close();
        }

        // Check for duplicate r_no if provided
        if ($r_no !== null) {
            $check_query = "SELECT asset_id FROM asset WHERE r_no = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $r_no);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception("An asset with this R number already exists");
            }
            $check_stmt->close();
        }

        // Insert basic asset information
        $query = "INSERT INTO asset (
            asset_name, serial_no, r_no, model, brand, type_id, location_id, 
            current_holder, previous_holder, purchase_date, warranty_expiry, 
            status, lan_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param(
            "sssssiissssss", 
            $asset_name, $serial_no, $r_no, $model, $brand, $type_id, $location_id,
            $current_holder, $previous_holder, $purchase_date, $warranty_expiry,
            $status, $lan_status
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $asset_id = $conn->insert_id;
        $stmt->close();

        // Handle computer/laptop specific fields
        if ($type_id == 1 || $type_id == 17) { // CPU or Laptop
            $processor = trim($_POST['processor'] ?? '');
            $processor_version = trim($_POST['processor_version'] ?? '');
            $ram_type = trim($_POST['ram_type'] ?? '');
            $ram_frequency = trim($_POST['ram_frequency'] ?? '');
            $ram_size = trim($_POST['ram_size'] ?? '');
            $storage_type = trim($_POST['storage_type'] ?? '');
            $storage_count = (int)($_POST['storage_count'] ?? 0);
            $graphics_card_available = $_POST['graphics_card_available'] ?? 'No';
            
            $query = "UPDATE asset SET 
                processor = ?, processor_version = ?, ram_type = ?, 
                ram_frequency = ?, ram_size = ?, storage_type = ?, 
                storage_count = ?, graphics_card_available = ?
                WHERE asset_id = ?";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param(
                "ssssssssi", 
                $processor, $processor_version, $ram_type,
                $ram_frequency, $ram_size, $storage_type,
                $storage_count, $graphics_card_available, $asset_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
            
            // Handle graphics card details if available
            if ($graphics_card_available == 'Yes') {
                $graphics_card_brand = trim($_POST['graphics_card_brand'] ?? '');
                $graphics_card_model = trim($_POST['graphics_card_model'] ?? '');
                $graphics_card_size = trim($_POST['graphics_card_size'] ?? '');
                
                $query = "UPDATE asset SET 
                    graphics_card_brand = ?, graphics_card_model = ?, graphics_card_size = ?
                    WHERE asset_id = ?";
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param(
                    "sssi", 
                    $graphics_card_brand, $graphics_card_model, $graphics_card_size, $asset_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Asset added successfully!";
        header("Location: manage_ast.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: add_ast.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Asset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-section h4 {
            color: #0d6efd;
            margin-bottom: 15px;
        }
        .computer-fields, .projector-fields, .printer-fields {
            display: none;
        }
        .required-field::after {
            content: " *";
            color: red;
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
        .page-header {
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        .page-title {
            flex-grow: 1;
            text-align: center;
        }
        .back-btn-container {
            margin-right: 15px;
        }
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
        <div class="page-header">
            <div class="back-btn-container">
                <a href="manage_ast.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Assets
                </a>
            </div>
            <h2 class="page-title">Add New Asset</h2>
        </div>
        
        <div class="form-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <form id="assetForm" action="add_ast.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h4>Basic Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asset_name" class="form-label required-field">Asset Name</label>
                            <input type="text" class="form-control" id="asset_name" name="asset_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type_id" class="form-label required-field">Asset Type</label>
                            <select class="form-select" id="type_id" name="type_id" required>
                                <option value="">Select Asset Type</option>
                                <?php foreach ($asset_types as $type): ?>
                                    <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="serial_no" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="serial_no" name="serial_no">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="r_no" class="form-label">R Number</label>
                            <input type="text" class="form-control" id="r_no" name="r_no">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location_id" class="form-label">Location</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= $location['location_id'] ?>"><?= htmlspecialchars($location['location_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="current_holder" class="form-label">Current Holder (Text)</label>
                            <input type="text" class="form-control" id="current_holder" name="current_holder">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="previous_holder" class="form-label">Previous Holder (Text)</label>
                            <input type="text" class="form-control" id="previous_holder" name="previous_holder">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="service">In Service</option>
                                <option value="scrapped">Scrapped</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="purchase_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="warranty_expiry" class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lan_status" class="form-label">LAN Status</label>
                            <select class="form-select" id="lan_status" name="lan_status">
                                <option value="">Select Status</option>
                                <option value="Working">Working</option>
                                <option value="Not working">Not working</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Computer/Laptop Specific Fields -->
                <div id="computer-fields" class="form-section computer-fields">
                    <h4>Computer/Laptop Specifications</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="processor" class="form-label">Processor</label>
                            <select class="form-select" id="processor" name="processor">
                                <option value="">Select Processor</option>
                                <option value="Dual core">Dual core</option>
                                <option value="Pentium">Pentium</option>
                                <option value="i3">i3</option>
                                <option value="i5">i5</option>
                                <option value="i7">i7</option>
                                <option value="Rizon 7">Rizon 7</option>
                                <option value="Rizon 9">Rizon 9</option>
                                <option value="MAC">MAC</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="processor_version" class="form-label">Processor Version</label>
                            <input type="text" class="form-control" id="processor_version" name="processor_version">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ram_type" class="form-label">RAM Type</label>
                            <select class="form-select" id="ram_type" name="ram_type">
                                <option value="">Select RAM Type</option>
                                <option value="DDR2">DDR2</option>
                                <option value="DDR3">DDR3</option>
                                <option value="DDR4">DDR4</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ram_frequency" class="form-label">RAM Frequency</label>
                            <input type="text" class="form-control" id="ram_frequency" name="ram_frequency">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ram_size" class="form-label">RAM Size (GB)</label>
                            <input type="text" class="form-control" id="ram_size" name="ram_size">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="storage_type" class="form-label">Storage Type</label>
                            <select class="form-select" id="storage_type" name="storage_type">
                                <option value="">Select Storage Type</option>
                                <option value="SSD">SSD</option>
                                <option value="HDD">HDD</option>
                                <option value="Both">Both</option>
                                <option value="NVM">NVM</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="storage_count" class="form-label">Storage Count</label>
                            <input type="number" class="form-control" id="storage_count" name="storage_count" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Graphics Card Available</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="graphics_card_available" id="graphics_yes" value="Yes">
                                <label class="form-check-label" for="graphics_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="graphics_card_available" id="graphics_no" value="No" checked>
                                <label class="form-check-label" for="graphics_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div id="graphics-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="graphics_card_brand" class="form-label">Graphics Card Brand</label>
                                <input type="text" class="form-control" id="graphics_card_brand" name="graphics_card_brand">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="graphics_card_model" class="form-label">Graphics Card Model</label>
                                <input type="text" class="form-control" id="graphics_card_model" name="graphics_card_model">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="graphics_card_size" class="form-label">Graphics Card Size (GB)</label>
                                <input type="text" class="form-control" id="graphics_card_size" name="graphics_card_size">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="button" id="clearForm" class="btn btn-secondary">
                        <i class="fas fa-eraser me-1"></i> Clear Form
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Add Asset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide fields based on asset type selection
        document.getElementById('type_id').addEventListener('change', function() {
            const typeId = parseInt(this.value);
            const computerFields = document.getElementById('computer-fields');
            
            // Hide all first
            computerFields.style.display = 'none';
            
            // Show relevant fields
            if (typeId === 1 || typeId === 17) { // CPU or Laptop
                computerFields.style.display = 'block';
            }
        });
        
        // Show/hide graphics card fields
        document.querySelectorAll('input[name="graphics_card_available"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('graphics-fields').style.display = 
                    this.value === 'Yes' ? 'block' : 'none';
            });
        });

        // Clear form button
        document.getElementById('clearForm').addEventListener('click', function() {
            document.getElementById('assetForm').reset();
            // Reset any dynamically shown fields
            document.getElementById('graphics-fields').style.display = 'none';
            // Reset radio buttons
            document.getElementById('graphics_no').checked = true;
        });

        // Initialize fields based on current selection (if page reloads)
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type_id');
            if (typeSelect.value) {
                typeSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>