<?php
require_once 'config.php';

// Only registration and superadmin can upload
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['registration', 'superadmin', 'teacher'])) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Create upload directory if it doesn't exist
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    mkdir($upload_dir . 'students/', 0777, true);
    mkdir($upload_dir . 'teachers/', 0777, true);
}

$response = ['success' => false, 'error' => 'Unknown error'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['photo'])) {
    $type = $_POST['type'] ?? ''; // 'student' or 'teacher'
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$type || !$id) {
        $response = ['success' => false, 'error' => 'Missing type or ID'];
        echo json_encode($response);
        exit;
    }
    
    $file = $_FILES['photo'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response = ['success' => false, 'error' => 'Upload error: ' . $file['error']];
        echo json_encode($response);
        exit;
    }
    
    // Check file size (max 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        $response = ['success' => false, 'error' => 'File too large (max 50MB)'];
        echo json_encode($response);
        exit;
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $response = ['success' => false, 'error' => 'Only JPG, PNG, GIF, and WEBP allowed'];
        echo json_encode($response);
        exit;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . '_' . $id . '_' . time() . '.' . $extension;
    
    if ($type == 'student') {
        $target_path = $upload_dir . 'students/' . $filename;
        
        // Verify student exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $response = ['success' => false, 'error' => 'Student not found'];
            echo json_encode($response);
            exit;
        }
        
        // Delete old photo if exists
        $stmt = $pdo->prepare("SELECT photo FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists($old)) {
            unlink($old);
        }
        
        // Save new photo
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $stmt = $pdo->prepare("UPDATE students SET photo = ? WHERE id = ?");
            if ($stmt->execute([$target_path, $id])) {
                $response = ['success' => true, 'path' => $target_path];
            } else {
                $response = ['success' => false, 'error' => 'Database update failed'];
            }
        } else {
            $response = ['success' => false, 'error' => 'Failed to save file'];
        }
        
    } elseif ($type == 'teacher') {
        $target_path = $upload_dir . 'teachers/' . $filename;
        
        // Verify teacher exists
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $response = ['success' => false, 'error' => 'Teacher not found'];
            echo json_encode($response);
            exit;
        }
        
        // Delete old photo if exists
        $stmt = $pdo->prepare("SELECT photo FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists($old)) {
            unlink($old);
        }
        
        // Save new photo
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $stmt = $pdo->prepare("UPDATE teachers SET photo = ? WHERE id = ?");
            if ($stmt->execute([$target_path, $id])) {
                $response = ['success' => true, 'path' => $target_path];
            } else {
                $response = ['success' => false, 'error' => 'Database update failed'];
            }
        } else {
            $response = ['success' => false, 'error' => 'Failed to save file'];
        }
    }
}

echo json_encode($response);
?>