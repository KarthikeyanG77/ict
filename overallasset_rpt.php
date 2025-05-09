<?php
// Database connection
require_once 'config.php';

// Initialize variables
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
$type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Get categories, locations, and types for dropdowns
$categories = [];
$locations = [];
$types = [];

// Always get all categories
$query = "SELECT * FROM asset_category ORDER BY category_name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get locations based on selected category (if any)
if ($category_id > 0) {
    $query = "SELECT * FROM location WHERE category_id = $category_id ORDER BY location_name";
} else {
    $query = "SELECT * FROM location ORDER BY location_name";
}
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}

// Always get all asset types
$query = "SELECT * FROM asset_type ORDER BY type_name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $types[] = $row;
}

// Build the base query
$query = "SELECT a.*, at.type_name, l.location_name, ac.category_name 
          FROM asset a
          LEFT JOIN asset_type at ON a.type_id = at.type_id
          LEFT JOIN location l ON a.location_id = l.location_id
          LEFT JOIN asset_category ac ON l.category_id = ac.category_id
          WHERE a.type_id NOT IN (19)"; // Exclude projectors

// Add filters
if ($category_id > 0) {
    $query .= " AND l.category_id = $category_id";
}

if ($location_id > 0) {
    $query .= " AND a.location_id = $location_id";
}

if ($type_id > 0) {
    $query .= " AND a.type_id = $type_id";
}

$query .= " ORDER BY l.location_name, at.type_name, a.asset_name";

// Handle Excel export
if ($export == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="asset_report_' . date('Y-m-d') . '.xls"');
    
    $result = $conn->query($query);
    
    echo "Asset Report\n\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    echo "ID\tName\tSerial No\tR No\tType\tBrand\tModel\tLocation (Category)\tStatus\tProcessor\tRAM\tStorage\n";
    
    while ($row = $result->fetch_assoc()) {
        echo $row['asset_id'] . "\t";
        echo $row['asset_name'] . "\t";
        echo $row['serial_no'] . "\t";
        echo $row['r_no'] . "\t";
        echo $row['type_name'] . "\t";
        echo $row['brand'] . "\t";
        echo $row['model'] . "\t";
        echo $row['location_name'] . ' (' . $row['category_name'] . ")\t";
        echo $row['status'] . "\t";
        echo $row['processor'] . " " . $row['processor_version'] . "\t";
        echo $row['ram_size'] . " " . $row['ram_type'] . "\t";
        echo $row['storage_type'] . " " . ($row['storage_count'] ?? '') . "\n";
    }
    exit;
}

// Get data for HTML display
$result = $conn->query($query);
$assets = [];
while ($row = $result->fetch_assoc()) {
    $assets[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .filter-section {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, button {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-top: 5px;
        }
        button.secondary {
            background-color: #6c757d;
        }
        button.danger {
            background-color: #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
                text-align: center;
                margin-bottom: 20px;
                font-size: 18px;
                font-weight: bold;
            }
            body {
                padding: 0;
            }
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-only">Asset Report - <?php echo date('Y-m-d'); ?></div>
        
        <div class="no-print">
            <h1>Asset Report</h1>
            
            <div class="filter-section">
                <form method="get" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="category_id">Asset Category:</label>
                            <select name="category_id" id="category_id" onchange="this.form.submit()">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo $category['category_id'] == $category_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="location_id">Location:</label>
                            <select name="location_id" id="location_id">
                                <option value="0">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['location_id']; ?>" <?php echo $location['location_id'] == $location_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="type_id">Asset Type:</label>
                            <select name="type_id" id="type_id">
                                <option value="0">All Types</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php echo $type['type_id'] == $type_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit">Apply Filters</button>
                        <button type="button" onclick="window.print()" class="secondary">Print Report</button>
                        <button type="submit" name="export" value="excel" class="secondary">Export to Excel</button>
                        <button type="button" onclick="window.location.href='dashboard.php'" class="danger">Back to Dashboard</button>
                    </div>
                </form>
            </div>
            
            <p>Showing <?php echo count($assets); ?> assets</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Serial No</th>
                    <th>R No</th>
                    <th>Type</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Location (Category)</th>
                    <th>Status</th>
                    <th>Processor</th>
                    <th>RAM</th>
                    <th>Storage</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($assets) > 0): ?>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($asset['asset_id']); ?></td>
                            <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['serial_no'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($asset['r_no'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($asset['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['brand']); ?></td>
                            <td><?php echo htmlspecialchars($asset['model']); ?></td>
                            <td><?php echo htmlspecialchars($asset['location_name']) . ' (' . htmlspecialchars($asset['category_name']) . ')'; ?></td>
                            <td><?php echo ucfirst($asset['status']); ?></td>
                            <td>
                                <?php 
                                echo htmlspecialchars($asset['processor'] ?? 'N/A');
                                if (!empty($asset['processor_version'])) {
                                    echo ' ' . htmlspecialchars($asset['processor_version']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                echo htmlspecialchars($asset['ram_size'] ?? 'N/A');
                                if (!empty($asset['ram_type'])) {
                                    echo ' ' . htmlspecialchars($asset['ram_type']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                echo htmlspecialchars($asset['storage_type'] ?? 'N/A');
                                if (!empty($asset['storage_count'])) {
                                    echo ' ' . htmlspecialchars($asset['storage_count']) . 'GB';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align: center;">No assets found matching your criteria</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        // Add confirmation before going back
        document.querySelector('button.danger').addEventListener('click', function() {
            if (confirm('Are you sure you want to go back to the dashboard?')) {
                window.location.href = 'dashboard.php';
            }
        });
        
        // Add print styles
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('tr:nth-child(even)').forEach(function(row) {
                row.style.backgroundColor = '#f9f9f9';
            });
        });
        
        // Auto-submit when category changes
        document.getElementById('category_id').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>