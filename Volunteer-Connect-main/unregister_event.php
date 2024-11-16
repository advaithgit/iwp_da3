// unregister_event.php
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

    // Check if registered and event is still open
    $check_stmt = $conn->prepare("SELECT e.status FROM volunteer_registration vr 
                                 INNER JOIN organization_events e ON vr.event_id = e.event_id 
                                 WHERE vr.volunteer_id = ? AND vr.event_id = ?");
    $check_stmt->bind_param("ii", $volunteer_id, $event_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Not registered for this event');
    }

    $event = $result->fetch_assoc();
    if ($event['status'] !== 'open') {
        throw new Exception('Cannot unregister from a closed event');
    }

    // Remove registration
    $unregister_stmt = $conn->prepare("DELETE FROM volunteer_registration WHERE volunteer_id = ? AND event_id = ?");
    $unregister_stmt->bind_param("ii", $volunteer_id, $event_id);
    $unregister_stmt->execute();

    // Update volunteers count
    $update_stmt = $conn->prepare("UPDATE organization_events SET volunteers_registered = volunteers_registered - 1 WHERE event_id = ?");
    $update_stmt->bind_param("i", $event_id);
    $update_stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Successfully unregistered from the event']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>