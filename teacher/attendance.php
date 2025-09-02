<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_attendance'])) {
        $class_id = (int)$_POST['class_id'];
        $attendance_date = $_POST['attendance_date'];
        $student_attendance = $_POST['student_attendance'] ?? [];
        
        // Validate class ownership
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ? AND status = 'active'");
        $stmt->execute([$class_id, $teacher_id]);
        if (!$stmt->fetch()) {
            setFlashMessage('error', 'Invalid class selected.');
            header('Location: attendance.php');
            exit();
        }
        
        // Check if attendance already exists for this date
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE class_id = ? AND attendance_date = ?");
        $stmt->execute([$class_id, $attendance_date]);
        if ($stmt->fetch()) {
            setFlashMessage('error', 'Attendance for this date has already been marked.');
            header('Location: attendance.php?class_id=' . $class_id . '&date=' . $attendance_date);
            exit();
        }
        
        // Get enrolled students
        $stmt = $pdo->prepare("SELECT student_id FROM class_enrollments WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $enrolled_students = $stmt->fetchAll();
        
        $success_count = 0;
        $errors = [];
        foreach ($enrolled_students as $enrollment) {
            $student_id = $enrollment['student_id'];
            $status = isset($student_attendance[$student_id]) ? $student_attendance[$student_id] : 'absent';
            
            try {
                $stmt = $pdo->prepare("INSERT INTO attendance (class_id, student_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$class_id, $student_id, $attendance_date, $status, $teacher_id])) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to mark attendance for student ID: $student_id";
                }
            } catch (PDOException $e) {
                // Check if it's a duplicate entry error
                if ($e->getCode() == 23000) {
                    $errors[] = "Attendance already exists for this date for student ID: $student_id";
                } else {
                    $errors[] = "Database error for student ID $student_id: " . $e->getMessage();
                }
            }
        }
        
        if ($success_count > 0) {
            // Send notifications for attendance marked
            notifyAttendanceMarked($pdo, $class_id, $attendance_date, $teacher_id);
            
            $message = "Attendance marked successfully for $success_count students!";
            if (!empty($errors)) {
                $message .= " Some errors occurred: " . implode(", ", array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " and " . (count($errors) - 3) . " more errors.";
                }
            }
            setFlashMessage('success', $message);
        } else {
            $error_message = 'Failed to mark attendance.';
            if (!empty($errors)) {
                $error_message .= " Errors: " . implode(", ", array_slice($errors, 0, 3));
            }
            setFlashMessage('error', $error_message);
        }
        
        header('Location: attendance.php?class_id=' . $class_id . '&date=' . $attendance_date);
        exit();
    }
    
    if (isset($_POST['update_attendance'])) {
        $attendance_id = (int)$_POST['attendance_id'];
        $new_status = $_POST['new_status'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT a.*, c.teacher_id FROM attendance a JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND c.teacher_id = ?");
        $stmt->execute([$attendance_id, $teacher_id]);
        $attendance = $stmt->fetch();
        
        if ($attendance) {
            $stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $attendance_id])) {
                setFlashMessage('success', 'Attendance updated successfully!');
            } else {
                setFlashMessage('error', 'Failed to update attendance.');
            }
        } else {
            setFlashMessage('error', 'Attendance record not found or access denied.');
        }
        
        header('Location: attendance.php?class_id=' . $class_id . '&date=' . $date);
        exit();
    }
}

// Get teacher's classes
$stmt = $pdo->prepare("SELECT id, name, subject FROM classes WHERE teacher_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Get attendance data for selected class and date
$attendance_data = [];
$students = [];
$existing_attendance = [];

if ($class_id > 0) {
    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email 
        FROM users u 
        JOIN class_enrollments ce ON u.id = ce.student_id 
        WHERE ce.class_id = ? 
        ORDER BY u.name
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    // Get existing attendance for the date
    $stmt = $pdo->prepare("
        SELECT a.*, u.name 
        FROM attendance a 
        JOIN users u ON a.student_id = u.id 
        WHERE a.class_id = ? AND a.attendance_date = ?
        ORDER BY u.name
    ");
    $stmt->execute([$class_id, $date]);
    $existing_attendance = $stmt->fetchAll();
    
    // Get attendance statistics for the month
    $month_start = date('Y-m-01', strtotime($date));
    $month_end = date('Y-m-t', strtotime($date));
    
    $stmt = $pdo->prepare("
        SELECT 
            a.student_id,
            u.name,
            COUNT(*) as total_days,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days
        FROM attendance a 
        JOIN users u ON a.student_id = u.id 
        WHERE a.class_id = ? AND a.attendance_date BETWEEN ? AND ?
        GROUP BY a.student_id, u.name
        ORDER BY u.name
    ");
    $stmt->execute([$class_id, $month_start, $month_end]);
    $attendance_data = $stmt->fetchAll();
}

$page_title = "Attendance Management";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Attendance Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                        <i class="fas fa-check"></i> Mark Attendance
                    </button>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportAttendance('csv')">
                                <i class="fas fa-file-csv"></i> Export as CSV
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportAttendance('excel')">
                                <i class="fas fa-file-excel"></i> Export as Excel
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportAttendance('spreadsheet')">
                                <i class="fas fa-table"></i> Export as Spreadsheet Format <span class="badge bg-success ms-1">New</span>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="exportAttendance('summary')">
                                <i class="fas fa-chart-bar"></i> Export Summary Report
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Information Alert -->
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i> 
                <strong>New Feature:</strong> Use the "Export as Spreadsheet Format" option to generate attendance reports in a grid format similar to traditional attendance sheets. 
                This format includes student names, daily attendance status, and summary statistics with color coding.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <!-- Class and Date Selection -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <label for="classSelect" class="form-label">Select Class</label>
                    <select class="form-select" id="classSelect" onchange="updateAttendanceView()">
                        <option value="">Choose a class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="dateSelect" class="form-label">Select Date</label>
                    <input type="date" class="form-control" id="dateSelect" value="<?= $date ?>" onchange="updateAttendanceView()">
                </div>
                <div class="col-md-3">
                    <label for="monthSelect" class="form-label">Select Month (for Spreadsheet Export)</label>
                    <input type="month" class="form-control" id="monthSelect" value="<?= date('Y-m') ?>" onchange="updateAttendanceView()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" onclick="setDate('<?= date('Y-m-d') ?>')">Today</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')">Yesterday</button>
                    </div>
                </div>
            </div>

            <?php if ($class_id > 0): ?>
                <!-- Daily Attendance View -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Daily Attendance - <?= formatDate($date) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($existing_attendance)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No attendance marked for this date yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_attendance as $record): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($record['name']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $record['status'] === 'present' ? 'success' : ($record['status'] === 'late' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($record['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editAttendance(<?= $record['id'] ?>, '<?= $record['status'] ?>')">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Attendance Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Attendance Summary - <?= date('F Y', strtotime($date)) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_data)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No attendance data available for this month.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Total Days</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Late</th>
                                            <th>Attendance Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_data as $record): ?>
                                            <?php 
                                            $attendance_rate = $record['total_days'] > 0 ? round(($record['present_days'] / $record['total_days']) * 100, 1) : 0;
                                            $rate_color = $attendance_rate >= 90 ? 'success' : ($attendance_rate >= 75 ? 'warning' : 'danger');
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($record['name']) ?></td>
                                                <td><?= $record['total_days'] ?></td>
                                                <td><span class="badge bg-success"><?= $record['present_days'] ?></span></td>
                                                <td><span class="badge bg-danger"><?= $record['absent_days'] ?></span></td>
                                                <td><span class="badge bg-warning"><?= $record['late_days'] ?></span></td>
                                                <td>
                                                    <span class="badge bg-<?= $rate_color ?>"><?= $attendance_rate ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please select a class to view attendance data.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Mark Attendance Modal -->
<div class="modal fade" id="markAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="markAttendanceForm">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_class_id" class="form-label">Class *</label>
                            <select class="form-select" name="class_id" id="modal_class_id" required>
                                <option value="">Select a class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_attendance_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" name="attendance_date" id="modal_attendance_date" value="<?= $date ?>" required>
                        </div>
                    </div>
                    
                    <div id="studentAttendanceList">
                        <?php if (!empty($students)): ?>
                            <h6>Mark attendance for students:</h6>
                            <div class="attendance-guide">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Quick Guide:</strong> Check the box next to a student's name to mark them as present, or use the status buttons to set specific attendance status. 
                                Use the quick action buttons below to mark multiple students at once.
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="quick-actions">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="markAllAs('present')">
                                        <i class="fas fa-check"></i> Mark All Present
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="markAllAs('late')">
                                        <i class="fas fa-clock"></i> Mark All Late
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAllAs('absent')">
                                        <i class="fas fa-times"></i> Mark All Absent
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="checkAllStudents()">
                                        <i class="fas fa-check-square"></i> Check All Students
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAll()">
                                        <i class="fas fa-eraser"></i> Clear All
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Student Checklist -->
                            <div class="attendance-checklist">
                                <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()" title="Select/Deselect All">
                                            </th>
                                            <th>Student Name</th>
                                            <th>Email</th>
                                            <th>Attendance Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input student-checkbox" 
                                                           data-student-id="<?= $student['id'] ?>" 
                                                           onchange="toggleStudentAttendance(<?= $student['id'] ?>)">
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($student['name']) ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($student['email']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <input type="radio" class="btn-check" 
                                                               name="student_attendance[<?= $student['id'] ?>]" 
                                                               id="present_<?= $student['id'] ?>" 
                                                               value="present" checked>
                                                        <label class="btn btn-outline-success" for="present_<?= $student['id'] ?>">
                                                            <i class="fas fa-check"></i> Present
                                                        </label>
                                                        
                                                        <input type="radio" class="btn-check" 
                                                               name="student_attendance[<?= $student['id'] ?>]" 
                                                               id="late_<?= $student['id'] ?>" 
                                                               value="late">
                                                        <label class="btn btn-outline-warning" for="late_<?= $student['id'] ?>">
                                                            <i class="fas fa-clock"></i> Late
                                                        </label>
                                                        
                                                        <input type="radio" class="btn-check" 
                                                               name="student_attendance[<?= $student['id'] ?>]" 
                                                               id="absent_<?= $student['id'] ?>" 
                                                               value="absent">
                                                        <label class="btn btn-outline-danger" for="absent_<?= $student['id'] ?>">
                                                            <i class="fas fa-times"></i> Absent
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            </div>
                            
                            <!-- Summary -->
                            <div class="attendance-summary mt-3">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <strong id="presentCount">0</strong><br>
                                        <small class="text-success">Present</small>
                                    </div>
                                    <div class="col-md-3">
                                        <strong id="lateCount">0</strong><br>
                                        <small class="text-warning">Late</small>
                                    </div>
                                    <div class="col-md-3">
                                        <strong id="absentCount">0</strong><br>
                                        <small class="text-danger">Absent</small>
                                    </div>
                                    <div class="col-md-3">
                                        <strong id="totalCount"><?= count($students) ?></strong><br>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No students enrolled in the selected class.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="mark_attendance" class="btn btn-success" <?= empty($students) ? 'disabled' : '' ?>>Mark Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="attendance_id" id="editAttendanceId">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="new_status" id="edit_present" value="present">
                            <label class="btn btn-outline-success" for="edit_present">Present</label>
                            
                            <input type="radio" class="btn-check" name="new_status" id="edit_late" value="late">
                            <label class="btn btn-outline-warning" for="edit_late">Late</label>
                            
                            <input type="radio" class="btn-check" name="new_status" id="edit_absent" value="absent">
                            <label class="btn btn-outline-danger" for="edit_absent">Absent</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_attendance" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateAttendanceView() {
    const classId = document.getElementById('classSelect').value;
    const date = document.getElementById('dateSelect').value;
    
    if (classId) {
        window.location.href = 'attendance.php?class_id=' + classId + '&date=' + date;
    }
}

function setDate(date) {
    document.getElementById('dateSelect').value = date;
    updateAttendanceView();
}

function editAttendance(attendanceId, currentStatus) {
    document.getElementById('editAttendanceId').value = attendanceId;
    
    // Reset all radio buttons
    document.getElementById('edit_present').checked = false;
    document.getElementById('edit_late').checked = false;
    document.getElementById('edit_absent').checked = false;
    
    // Check the current status
    document.getElementById('edit_' + currentStatus).checked = true;
    
    new bootstrap.Modal(document.getElementById('editAttendanceModal')).show();
}

function exportAttendance(format = 'csv') {
    const classId = document.getElementById('classSelect').value;
    const date = document.getElementById('dateSelect').value;
    
    if (!classId) {
        alert('Please select a class first.');
        return;
    }
    
    // Create export URL based on format
    let exportUrl;
    if (format === 'excel') {
        exportUrl = 'export_attendance_excel.php?class_id=' + classId + '&date=' + date;
    } else if (format === 'summary') {
        exportUrl = 'export_attendance_summary.php?class_id=' + classId + '&date=' + date;
    } else if (format === 'spreadsheet') {
        const month = document.getElementById('monthSelect').value;
        exportUrl = 'export_attendance_spreadsheet.php?class_id=' + classId + '&month=' + month;
    } else {
        exportUrl = 'export_attendance.php?class_id=' + classId + '&date=' + date;
    }
    
    window.open(exportUrl, '_blank');
}

// Update student list when class changes in modal
document.getElementById('modal_class_id').addEventListener('change', function() {
    const classId = this.value;
    if (classId) {
        // Reload the page with the new class to get students
        window.location.href = 'attendance.php?class_id=' + classId + '&date=' + document.getElementById('modal_attendance_date').value;
    }
});

// Function to mark all students as a specific status
function markAllAs(status) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const radioButtons = document.querySelectorAll('input[name^="student_attendance"]');
    
    // Reset all radio buttons first
    radioButtons.forEach(radio => {
        radio.checked = false;
    });
    
    // Set the specified status for all students
    const statusRadios = document.querySelectorAll(`input[name^="student_attendance"][value="${status}"]`);
    statusRadios.forEach(radio => {
        radio.checked = true;
    });
    
    // Update counts
    updateAttendanceCounts();
}

// Function to clear all student statuses
function clearAll() {
    const radioButtons = document.querySelectorAll('input[name^="student_attendance"]');
    radioButtons.forEach(radio => {
        radio.checked = false;
    });
    
    // Reset counts
    const presentElement = document.getElementById('presentCount');
    const lateElement = document.getElementById('lateCount');
    const absentElement = document.getElementById('absentCount');
    
    if (presentElement) presentElement.textContent = '0';
    if (lateElement) lateElement.textContent = '0';
    if (absentElement) absentElement.textContent = '0';
}

// Function to toggle selectAll checkbox
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const isChecked = selectAll.checked;

    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
        // Trigger the change event for each checkbox
        const studentId = checkbox.getAttribute('data-student-id');
        toggleStudentAttendance(studentId);
    });
}

// Function to check all students (mark as present)
function checkAllStudents() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
        const studentId = checkbox.getAttribute('data-student-id');
        toggleStudentAttendance(studentId);
    });
    document.getElementById('selectAll').checked = true;
}

// Function to toggle student attendance based on checkbox
function toggleStudentAttendance(studentId) {
    const checkbox = document.querySelector(`.student-checkbox[data-student-id="${studentId}"]`);
    const presentRadio = document.getElementById(`present_${studentId}`);
    const lateRadio = document.getElementById(`late_${studentId}`);
    const absentRadio = document.getElementById(`absent_${studentId}`);

    if (checkbox.checked) {
        // If checkbox is checked, mark as present by default
        presentRadio.checked = true;
        lateRadio.checked = false;
        absentRadio.checked = false;
    } else {
        // If checkbox is unchecked, mark as absent
        presentRadio.checked = false;
        lateRadio.checked = false;
        absentRadio.checked = true;
    }
    
    updateAttendanceCounts();
}

// Function to update attendance counts
function updateAttendanceCounts() {
    const presentCount = document.querySelectorAll('input[name^="student_attendance"][value="present"]:checked').length;
    const lateCount = document.querySelectorAll('input[name^="student_attendance"][value="late"]:checked').length;
    const absentCount = document.querySelectorAll('input[name^="student_attendance"][value="absent"]:checked').length;
    
    const presentElement = document.getElementById('presentCount');
    const lateElement = document.getElementById('lateCount');
    const absentElement = document.getElementById('absentCount');
    
    if (presentElement) presentElement.textContent = presentCount;
    if (lateElement) lateElement.textContent = lateCount;
    if (absentElement) absentElement.textContent = absentCount;
}

// Initialize counts when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateAttendanceCounts();
    
    // Add event listeners for radio button changes
    const radioButtons = document.querySelectorAll('input[name^="student_attendance"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', updateAttendanceCounts);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
