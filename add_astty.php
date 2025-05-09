<?php
session_start();
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();

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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $typeName = trim($_POST['type_name']);

        if (empty($typeName)) {
            $error = "Type name is required!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO asset_type (type_name) VALUES (?)");
            $stmt->execute([$typeName]);

            $_SESSION['message'] = "Asset type added successfully!";
            header("Location: manage_astty.php");
            exit;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "This asset type already exists!";
        } else {
            $error = "Error adding asset type: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Asset Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container { 
            max-width: 600px; 
            margin-top: 50px; 
        }
        .card { 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .form-label { 
            font-weight: 500; 
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
        .top-navigation {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .back-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 1.1rem;
        }
        .back-btn:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Top Navigation with Back Button -->
    <div class="top-navigation">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

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
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Add New Asset Type</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="type_name" class="form-label">Type Name</label>
                        <input type="text" class="form-control" id="type_name" name="type_name" required>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="manage_astty.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>