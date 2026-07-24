<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $teacher_id = $_SESSION['teacher_id'];
    
    // Get students for this class
    $stmt = $pdo->prepare("
        SELECT s.* FROM students s 
        WHERE s.teacher_id = ? AND s.grade = ? AND s.class_section = ?
        ORDER BY s.roll_number
    ");
    $stmt->execute([$teacher_id, $grade, $section]);
    $students = $stmt->fetchAll();
    ?>
    
    <form method="POST" action="mark_saturday_attendance.php">
        <input type="hidden" name="date" value="<?php echo $date; ?>">
        <input type="hidden" name="grade" value="<?php echo $grade; ?>">
        <input type="hidden" name="section" value="<?php echo $section; ?>">
        
        <p style="color:#666; margin-bottom:20px;">
            Marking Saturday attendance for <strong><?php echo date('d F Y', strtotime($date)); ?></strong><br>
            Class: <strong><?php echo $grade . $section; ?></strong>
        </p>
        
        <div class="table-container" style="max-height:400px; overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <th>Attendance</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $student): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td>
                            <div style="display:flex; gap:10px;">
                                <label style="display:flex; align-items:center; gap:5px;">
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="Present" checked>
                                    <span style="width:10px;height:10px;background:#4CAF50;border-radius:50%;"></span>
                                </label>
                                <label style="display:flex; align-items:center; gap:5px;">
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="Absent">
                                    <span style="width:10px;height:10px;background:#f44336;border-radius:50%;"></span>
                                </label>
                                <label style="display:flex; align-items:center; gap:5px;">
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="Late">
                                    <span style="width:10px;height:10px;background:#FF9800;border-radius:50%;"></span>
                                </label>
                            </div>
                        </td>
                        <td>
                            <input type="text" name="remarks[<?php echo $student['id']; ?>]" placeholder="Optional" style="width:100%; padding:8px;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="display:flex; gap:15px; margin-top:25px;">
            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Save Saturday Attendance
            </button>
            <button type="button" onclick="closeSaturdayModal()" style="background:#f5f5f5; color:#666; border:none; padding:14px 25px; border-radius:10px; cursor:pointer;">
                Cancel
            </button>
        </div>
    </form>
    <?php
}
?>