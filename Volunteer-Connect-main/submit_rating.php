<?php
session_start();
include('db_connection.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_id = $_POST['event_id'];
    $organization_id = $_POST['organization_id'];
    $volunteer_id = $_SESSION['volunteer_id'] ?? 1; // Fallback for testing
    $organization_rating = $_POST['organization_rating'];
    $experience_rating = $_POST['experience_rating'];
    $impact_rating = $_POST['impact_rating'];
    $support_rating = $_POST['support_rating'];
    $team_rating = $_POST['team_rating'];
    $testimonial = $_POST['testimonial'];

    try {
        $pdo->beginTransaction();

        // Insert into event_testimonials
        $stmt = $pdo->prepare("INSERT INTO event_testimonials (event_id, volunteer_id, rating, feedback, created_at) 
                               VALUES (?, ?, ?, ?, NOW())");
        $average_rating = ($organization_rating + $experience_rating + $impact_rating + $support_rating + $team_rating) / 5;
        $stmt->execute([$event_id, $volunteer_id, $average_rating, $testimonial]);

        // Update organization_events
        $stmt = $pdo->prepare("UPDATE organization_events 
                               SET rating = (SELECT AVG(rating) FROM event_testimonials WHERE event_id = ?)
                               WHERE event_id = ?");
        $stmt->execute([$event_id, $event_id]);

        // Update organization_profile
        $stmt = $pdo->prepare("UPDATE organization_profile 
                               SET star_rating = (SELECT AVG(rating) FROM organization_events WHERE organization_id = ?)
                               WHERE id = ?");
        $stmt->execute([$organization_id, $organization_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Rating submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}