<?php
include('includes/db_connection.php');

header('Content-Type: application/json');

// Query to fetch events
$sql = "SELECT event_id, event_name, event_description, start_date, end_date, location, skills_needed, perks, age_group, num_volunteers_needed, volunteers_registered FROM organization_events WHERE status = 'open'";
$result = $conn->query($sql);

$events = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Return JSON response
echo json_encode($events);

$conn->close();
?>
