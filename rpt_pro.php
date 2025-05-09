<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$currentUserName = "User";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projector Assets Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print, .dataTables_length, .dataTables_filter, 
            .dataTables_info, .dataTables_paginate, .dt-buttons {
                display: none !important;
            }
            body {
                font-size: 12px;
            }
            .table {
                width: 100% !important;
            }
        }
        .report-header {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .filter-section {
            background-color: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-weight: 500;
        }
        .badge-active {
            background-color: #28a745;
        }
        .badge-service {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-scrapped {
            background-color: #dc3545;
        }
        .vertical-form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- User Reference Section -->
        <div class="alert alert-info no-print">
            <strong>Logged in as:</strong> <?php echo htmlspecialchars($currentUserName); ?>
        </div>

        <div class="report-header">
            <h2 class="text-center">Projector Assets Detailed Report</h2>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="get" action="">
                <div class="row">
                    <!-- Asset Type Filter -->
                    <div class="col-md-4">
                        <div class="vertical-form-group">
                            <label for="asset_type" class="form-label">Asset Type</label>
                            <select class="form-select" id="asset_type" name="asset_type">
                                <option value="">All Types</option>
                                <?php
                                $sql = "SELECT type_id, type_name FROM asset_type WHERE type_name = 'Projector'";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        $selected = (isset($_GET['asset_type']) && $_GET['asset_type'] == $row['type_id']) ? 'selected' : '';
                                        echo "<option value='".$row['type_id']."' $selected>".$row['type_name']."</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Location Filter -->
                    <div class="col-md-4">
                        <div class="vertical-form-group">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">All Locations</option>
                                <?php
                                $sql = "SELECT location_id, location_name FROM location ORDER BY location_name";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        $selected = (isset($_GET['location']) && $_GET['location'] == $row['location_id']) ? 'selected' : '';
                                        echo "<option value='".$row['location_id']."' $selected>".$row['location_name']."</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-md-4">
                        <div class="vertical-form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="service" <?php echo (isset($_GET['status']) && $_GET['status'] == 'service') ? 'selected' : ''; ?>>In Service</option>
                                <option value="scrapped" <?php echo (isset($_GET['status']) && $_GET['status'] == 'scrapped') ? 'selected' : ''; ?>>Scrapped</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Table -->
        <div class="table-responsive">
            <table id="projectorTable" class="table table-bordered table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Asset ID</th>
                        <th>Asset Name</th>
                        <th>Serial No</th>
                        <th>R No</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Brand</th>
                        <th>Speaker</th>
                        <th>Projector Screen</th>
                        <th>Cable Type</th>
                        <th>Logic Box</th>
                        <th>Lumens</th>
                        <th>Resolution</th>
                        <th>Status</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Build query based on filters
                    $sql = "SELECT 
                                a.asset_id, a.asset_name, a.serial_no, a.r_no, 
                                at.type_name, l.location_name, a.brand, 
                                a.has_speaker, a.projector_type, a.lumens, 
                                a.resolution, a.status, a.printer_type as cable_type,
                                a.processor as logic_box, a.model as remark
                            FROM asset a
                            JOIN asset_type at ON a.type_id = at.type_id
                            JOIN location l ON a.location_id = l.location_id
                            WHERE at.type_name = 'Projector'";
                    
                    if (isset($_GET['asset_type']) && !empty($_GET['asset_type'])) {
                        $asset_type = $conn->real_escape_string($_GET['asset_type']);
                        $sql .= " AND a.type_id = '$asset_type'";
                    }
                    
                    if (isset($_GET['location']) && !empty($_GET['location'])) {
                        $location = $conn->real_escape_string($_GET['location']);
                        $sql .= " AND a.location_id = '$location'";
                    }
                    
                    if (isset($_GET['status']) && !empty($_GET['status'])) {
                        $status = $conn->real_escape_string($_GET['status']);
                        $sql .= " AND a.status = '$status'";
                    }
                    
                    $sql .= " ORDER BY a.asset_name";
                    
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>".$row['asset_id']."</td>";
                            echo "<td>".htmlspecialchars($row['asset_name'])."</td>";
                            echo "<td>".($row['serial_no'] ? htmlspecialchars($row['serial_no']) : 'N/A')."</td>";
                            echo "<td>".($row['r_no'] ? htmlspecialchars($row['r_no']) : 'N/A')."</td>";
                            echo "<td>".htmlspecialchars($row['type_name'])."</td>";
                            echo "<td>".htmlspecialchars($row['location_name'])."</td>";
                            echo "<td>".($row['brand'] ? htmlspecialchars($row['brand']) : 'N/A')."</td>";
                            echo "<td>".($row['has_speaker'] ? htmlspecialchars($row['has_speaker']) : 'N/A')."</td>";
                            echo "<td>".($row['projector_type'] ? htmlspecialchars($row['projector_type']) : 'N/A')."</td>";
                            echo "<td>".($row['cable_type'] ? htmlspecialchars($row['cable_type']) : 'N/A')."</td>";
                            echo "<td>".($row['logic_box'] ? htmlspecialchars($row['logic_box']) : 'N/A')."</td>";
                            echo "<td>".($row['lumens'] ? htmlspecialchars($row['lumens']) : 'N/A')."</td>";
                            echo "<td>".($row['resolution'] ? htmlspecialchars($row['resolution']) : 'N/A')."</td>";
                            
                            // Status with badge
                            echo "<td>";
                            switch($row['status']) {
                                case 'active':
                                    echo "<span class='badge badge-active'>Active</span>";
                                    break;
                                case 'service':
                                    echo "<span class='badge badge-service'>In Service</span>";
                                    break;
                                case 'scrapped':
                                    echo "<span class='badge badge-scrapped'>Scrapped</span>";
                                    break;
                                default:
                                    echo "<span class='badge bg-secondary'>".htmlspecialchars($row['status'])."</span>";
                            }
                            echo "</td>";
                            
                            echo "<td>".($row['remark'] ? htmlspecialchars($row['remark']) : 'N/A')."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='15' class='text-center'>No projector assets found with the selected filters</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Action Buttons -->
        <div class="mt-3 no-print">
            <button onclick="window.print()" class="btn btn-success me-2">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button id="exportExcel" class="btn btn-primary me-2">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with export buttons
            $('#projectorTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        title: 'Projector_Assets_Report',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(xlsx) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            $('row:first c', sheet).attr('s', '32'); // Bold headers
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        title: 'Projector Assets Report',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(win) {
                            $(win.document.body).find('h1').css('text-align', 'center');
                            $(win.document.body).find('table').addClass('compact').css('font-size', '10pt');
                        }
                    }
                ],
                pageLength: 25,
                responsive: true
            });

            // Custom Excel export button
            $('#exportExcel').click(function() {
                $('#projectorTable').DataTable().button('.buttons-excel').trigger();
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>