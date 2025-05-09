<?php
// Start session and include config file
session_start();
require_once 'config.php';

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

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle search
$search = '';
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
}

// Build query with search condition
$query = "SELECT a.*, at.type_name, l.location_name 
          FROM asset a
          JOIN asset_type at ON a.type_id = at.type_id
          JOIN location l ON a.location_id = l.location_id
          WHERE a.asset_name LIKE '%$search%' OR 
                a.serial_no LIKE '%$search%' OR 
                a.r_no LIKE '%$search%' OR 
                a.model LIKE '%$search%' OR 
                a.brand LIKE '%$search%' OR 
                at.type_name LIKE '%$search%' OR 
                l.location_name LIKE '%$search%'
          ORDER BY a.asset_id DESC";

$result = mysqli_query($conn, $query);

// Check if query was successful
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Handle delete message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <center> <title>Manage Assets</title></center>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .asset-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .asset-card:hover {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .asset-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .asset-details {
            display: flex;
            flex-wrap: wrap;
        }
        .detail-col {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 5px 0;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        .status-service {
            color: #ffc107;
            font-weight: bold;
        }
        .status-scrapped {
            color: #6c757d;
            font-weight: bold;
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
        .header-row {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- User Info Section - Top Right -->
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

    <div class="container-fluid mt-4">
        <!-- First Row - Back Button and Title -->
        <div class="row header-row">
            <div class="col-md-6">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 style="display: inline-block; margin-left: 10px;">Manage Assets</h2>
            </div>
        </div>

        <!-- Second Row - Search and Add New -->
        <div class="row mb-4">
            <div class="col-md-6">
                <!-- Search will be aligned left -->
            </div>
            <div class="col-md-6 text-end">
    <a href="add_ast.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-1"></i> Add New Asset
    </a>
</div>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search assets..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                        <?php if ($search): ?>
                            <a href="manage_ast.php" class="btn btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row">
            <?php while ($asset = mysqli_fetch_assoc($result)): ?>
                <div class="col-md-6">
                    <div class="asset-card">
                        <div class="asset-header d-flex justify-content-between">
                            <h5><?php echo htmlspecialchars($asset['asset_name']); ?></h5>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($asset['type_name']); ?></span>
                        </div>
                        
                        <div class="asset-details">
                            <div class="detail-col">
                                <p><strong>Serial No:</strong> <?php echo htmlspecialchars($asset['serial_no']); ?></p>
                                <p><strong>R No:</strong> <?php echo htmlspecialchars($asset['r_no']); ?></p>
                                <p><strong>Model:</strong> <?php echo htmlspecialchars($asset['model']); ?></p>
                            </div>
                            <div class="detail-col">
                                <p><strong>Brand:</strong> <?php echo htmlspecialchars($asset['brand']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($asset['location_name']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="status-<?php echo strtolower($asset['status']); ?>">
                                        <?php echo htmlspecialchars($asset['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <div>
                                <a href="edit_ast.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="del_ast.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                            </div>
                            <a href="view_ast.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-outline-info">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <?php if (mysqli_num_rows($result) == 0): ?>
                <div class="col-12">
                    <div class="alert alert-info">No assets found. <a href="add_ast.php">Add a new asset</a> to get started.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.asset-table').DataTable({
                responsive: true
            });
        });
    </script>
</body>
</html>
<?php
// Close the database connection
mysqli_close($conn);
?>