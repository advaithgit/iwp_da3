<?php
session_start();
require_once 'db_connection.php';

$volunteer_id = $_SESSION['volunteer_id'] ?? null;
$event_id = $_POST['event_id'] ?? null;

if (!$volunteer_id || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $conn->begin_transaction();

    // Check if already registered
    $check_stmt = $conn->prepare("SELECT 1 FROM volunteer_registration WHERE volunteer_id = ? AND event_id = ?");
    $check_stmt->bind_param("ii", $volunteer_id, $event_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('Already registered for this event');
    }

    // Check if event is still open and has space
    $event_stmt = $conn->prepare("SELECT num_volunteers_needed, volunteers_registered, status FROM organization_events WHERE event_id = ?");
    $event_stmt->bind_param("i", $event_id);
    $event_stmt->execute();
    $event = $event_stmt->get_result()->fetch_assoc();

    if ($event['status'] !== 'open') {
        throw new Exception('Event is no longer open for registration');
    }

    if ($event['volunteers_registered'] >= $event['num_volunteers_needed']) {
        throw new Exception('Event is already full');
    }

    // Register volunteer
    $register_stmt = $conn->prepare("INSERT INTO volunteer_registration (volunteer_id, event_id, registration_date) VALUES (?, ?, CURDATE())");
    $register_stmt->bind_param("ii", $volunteer_id, $event_id);
    $register_stmt->execute();

    // Update volunteers count
    $update_stmt = $conn->prepare("UPDATE organization_events SET volunteers_registered = volunteers_registered + 1 WHERE event_id = ?");
    $update_stmt->bind_param("i", $event_id);
    $update_stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Successfully registered for the event']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>