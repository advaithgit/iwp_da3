<?php
// get_events.php
session_start();
require_once 'db_connection.php';

$volunteer_id = $_SESSION['volunteer_id'] ?? null;
if (!$volunteer_id) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$tab = $_POST['tab'] ?? '';
$filters = [];
parse_str($_POST['filters'] ?? '', $filters);

// Base query parts
$select = "SELECT e.*, 
           COALESCE(vr.volunteers_registered, 0) as volunteers_registered,
           CASE 
               WHEN DATEDIFF(e.end_date, e.start_date) = 0 THEN 'short'
               WHEN DATEDIFF(e.end_date, e.start_date) BETWEEN 1 AND 2 THEN 'medium'
               ELSE 'long'
           END as duration";
$from = "FROM organization_events e";
$where = "WHERE 1=1";
$params = [];
$types = "";

// Add joins and conditions based on tab
switch ($tab) {
    case 'upcoming-tab':
        $where .= " AND e.status = 'open' AND e.start_date >= CURDATE()";
        $where .= " AND e.event_id NOT IN (SELECT event_id FROM volunteer_registration WHERE volunteer_id = ?)";
        $params[] = $volunteer_id;
        $types .= "i";
        break;
        
    case 'registered-tab':
        $from .= " INNER JOIN volunteer_registration vr ON e.event_id = vr.event_id";
        $where .= " AND vr.volunteer_id = ? AND e.status = 'open'";
        $params[] = $volunteer_id;
        $types .= "i";
        break;
        
    case 'past-tab':
        $from .= " INNER JOIN volunteer_registration vr ON e.event_id = vr.event_id";
        $where .= " AND vr.volunteer_id = ? AND e.status = 'closed'";
        $params[] = $volunteer_id;
        $types .= "i";
        break;
}

// Apply filters
if (!empty($filters['age_group'])) {
    $placeholders = str_repeat('?,', count($filters['age_group']) - 1) . '?';
    $where .= " AND e.age_group IN ($placeholders)";
    foreach ($filters['age_group'] as $age) {
        $params[] = $age;
        $types .= "s";
    }
}

if (!empty($filters['event_type'])) {
    $placeholders = str_repeat('?,', count($filters['event_type']) - 1) . '?';
    $where .= " AND e.type_of_event IN ($placeholders)";
    foreach ($filters['event_type'] as $type) {
        $params[] = $type;
        $types .= "s";
    }
}

if (!empty($filters['skills'])) {
    $placeholders = str_repeat('?,', count($filters['skills']) - 1) . '?';
    $where .= " AND e.skills_needed IN ($placeholders)";
    foreach ($filters['skills'] as $skill) {
        $params[] = $skill;
        $types .= "s";
    }
}

if (!empty($filters['duration'])) {
    $duration_conditions = [];
    foreach ($filters['duration'] as $duration) {
        switch ($duration) {
            case 'short':
                $duration_conditions[] = "DATEDIFF(e.end_date, e.start_date) = 0";
                break;
            case 'medium':
                $duration_conditions[] = "DATEDIFF(e.end_date, e.start_date) BETWEEN 1 AND 2";
                break;
            case 'long':
                $duration_conditions[] = "DATEDIFF(e.end_date, e.start_date) > 2";
                break;
        }
    }
    if (!empty($duration_conditions)) {
        $where .= " AND (" . implode(" OR ", $duration_conditions) . ")";
    }
}

// Prepare and execute query
$query = "$select $from $where ORDER BY e.start_date ASC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$events = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($events);
?>