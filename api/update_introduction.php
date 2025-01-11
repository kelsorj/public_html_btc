<?php
ob_start();
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$conn->begin_transaction();

try {
    // Update introduction content
    $update_query = "UPDATE introduction SET title = ?, content = ? WHERE id = 1";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ss", $_POST['title'], $_POST['content']);
    $stmt->execute();

    // Update existing timeline entries
    if (isset($_POST['entries']) && is_array($_POST['entries'])) {
        $update_entry_query = "UPDATE timeline_entries SET year = ?, event = ? WHERE id = ?";
        $stmt = $conn->prepare($update_entry_query);
        
        foreach ($_POST['entries'] as $id => $entry) {
            $stmt->bind_param("isi", $entry['year'], $entry['event'], $id);
            $stmt->execute();
        }
    }

    // Add new timeline entries
    if (isset($_POST['new_entries']) && is_array($_POST['new_entries'])) {
        $insert_query = "INSERT INTO timeline_entries (year, event, created_by) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        foreach ($_POST['new_entries'] as $entry) {
            $stmt->bind_param("isi", $entry['year'], $entry['event'], $_SESSION['user_id']);
            $stmt->execute();
        }
    }

    $conn->commit();
    
    // Clean output buffer before sending headers
    ob_end_clean();
    
    $_SESSION['success_message'] = 'Introduction updated successfully';
    header('Location: ../introduction.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating introduction: " . $e->getMessage());
    
    // Clean output buffer before sending headers
    ob_end_clean();
    
    $_SESSION['error_message'] = 'Error updating introduction. Please try again.';
    header('Location: ../edit_introduction.php');
    exit;
}
?> 