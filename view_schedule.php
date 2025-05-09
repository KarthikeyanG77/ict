<?php
session_start();
require_once 'config.php';

// Get lab ID from query string if specified
$lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;

// Fetch all labs for dropdown
$labs_query = "SELECT location_id, location_name FROM location WHERE category_id = 1";
$labs_result = mysqli_query($conn, $labs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lab Schedules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4>Lab Schedules</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="lab_filter" class="form-label">Filter by Lab:</label>
                                    <select class="form-select" id="lab_filter" name="lab_id" onchange="this.form.submit()">
                                        <option value="0">All Labs</option>
                                        <?php while($lab = mysqli_fetch_assoc($labs_result)): ?>
                                            <option value="<?php echo $lab['location_id']; ?>" 
                                                <?php echo ($lab_id == $lab['location_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lab['location_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                        
                        <div class="table-responsive">
                            <table id="schedulesTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Lab</th>
                                        <th>Day</th>
                                        <th>Time Slot</th>
                                        <th>Subject</th>
                                        <th>Faculty</th>
                                        <th>Class Group</th>
                                        <th>Semester</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Build query based on filter
                                    $query = "SELECT s.*, l.location_name 
                                             FROM lab_schedule s
                                             JOIN location l ON s.lab_id = l.location_id";
                                    
                                    if ($lab_id > 0) {
                                        $query .= " WHERE s.lab_id = $lab_id";
                                    }
                                    
                                    $query .= " ORDER BY l.location_name, 
                                                FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                                                s.time_slot";
                                    
                                    $result = mysqli_query($conn, $query);
                                    
                                    while($schedule = mysqli_fetch_assoc($result)):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['location_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['time_slot']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                            <?php if (!empty($schedule['subject_code'])): ?>
                                                <br><small>(<?php echo htmlspecialchars($schedule['subject_code']); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($schedule['faculty_name']); ?>
                                            <?php if (!empty($schedule['faculty_email'])): ?>
                                                <br><small><?php echo htmlspecialchars($schedule['faculty_email']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($schedule['class_group']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['semester']); ?></td>
                                        <td>
                                            <a href="edit_schedule.php?id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <a href="delete_schedule.php?id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#schedulesTable').DataTable({
                responsive: true,
                order: [[1, 'asc'], [2, 'asc'] // Order by day then time slot
            });
        });
    </script>
</body>
</html>