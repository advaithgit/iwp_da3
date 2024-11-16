<?php
// Database connection settings
$host = 'localhost';
$dbname = 'volunteer-connect-one-org';
$username = 'root';
$password = '';

// Create a new PDO instance
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Build the query based on filters
$query = "SELECT * FROM organization_events WHERE status = 'open'";
$params = array();

if (!empty($_GET['age'])) {
    $ageGroups = implode("','", $_GET['age']);
    $query .= " AND age_group IN ('$ageGroups')";
}

if (!empty($_GET['event_type'])) {
    $query .= " AND event_type = :event_type";
    $params[':event_type'] = $_GET['event_type'];
}

if (!empty($_GET['skills'])) {
    $skills = implode("%' OR skills_needed LIKE '%", $_GET['skills']);
    $query .= " AND (skills_needed LIKE '%$skills%')";
}

if (!empty($_GET['duration'])) {
    $query .= " AND CASE 
                    WHEN :duration = 'short' THEN DATEDIFF(end_date, start_date) = 0
                    WHEN :duration = 'medium' THEN DATEDIFF(end_date, start_date) BETWEEN 1 AND 2
                    WHEN :duration = 'long' THEN DATEDIFF(end_date, start_date) > 2
                END";
    $params[':duration'] = $_GET['duration'];
}

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate HTML for filtered events
foreach ($events as $event) {
    echo '<div class="col-md-4 mb-4">';
    echo '<div class="card h-100">';
    echo '<img src="https://via.placeholder.com/300x200" class="card-img-top" alt="Event Image">';
    echo '<div class="card-body d-flex flex-column">';
    echo '<h5 class="card-title">' . htmlspecialchars($event['event_name']) . '</h5>';
    echo '<p class="card-text">' . htmlspecialchars($event['event_description']) . '</p>';
    echo '<p class="card-text">Location: ' . htmlspecialchars($event['location']) . '</p>';
    echo '<p class="card-text">Date: ' . htmlspecialchars($event['start_date']) . ' - ' . htmlspecialchars($event['end_date']) . '</p>';
    echo '<p class="card-text">Type: ' . htmlspecialchars($event['event_type']) . '</p>';
    echo '<p class="card-text">Skills Needed: ' . htmlspecialchars($event['skills_needed']) . '</p>';
    echo '<p class="card-text">Age Group: ' . htmlspecialchars($event['age_group']) . '</p>';
    echo '<button class="btn btn-success mt-auto register-btn" data-event-id="' . $event['event_id'] . '">Register</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}