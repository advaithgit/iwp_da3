<?php
session_start();
include('db_connection.php');

// Check if event_id is provided
if (!isset($_GET['event_id'])) {
    die("No event specified");
}

$event_id = $_GET['event_id'];

// Fetch event details
$stmt = $pdo->prepare("SELECT e.*, o.name as organization_name, o.id as organization_id 
                       FROM organization_events e 
                       JOIN organization_profile o ON e.organization_id = o.id 
                       WHERE e.event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $organization_rating = $_POST['organization_rating'];
    $experience_rating = $_POST['experience_rating'];
    $impact_rating = $_POST['impact_rating'];
    $support_rating = $_POST['support_rating'];
    $team_rating = $_POST['team_rating'];
    $testimonial = $_POST['testimonial'];
    
    $average_rating = ($organization_rating + $experience_rating + $impact_rating + $support_rating + $team_rating) / 5;
    
    // Insert rating into event_testimonials
    $stmt = $pdo->prepare("INSERT INTO event_testimonials (event_id, volunteer_id, rating, feedback, created_at) 
                           VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$event_id, $_SESSION['volunteer_id'], $average_rating, $testimonial]);
    
    // Update organization's star rating
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM event_testimonials WHERE event_id IN 
                           (SELECT event_id FROM organization_events WHERE organization_id = ?)");
    $stmt->execute([$event['organization_id']]);
    $org_avg_rating = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'];
    
    $stmt = $pdo->prepare("UPDATE organization_profile SET star_rating = ? WHERE id = ?");
    $stmt->execute([$org_avg_rating, $event['organization_id']]);
    
    $_SESSION['message'] = "Thank you for your rating!";
    header("Location: events.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Event</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .rating-card { max-width: 600px; margin: 2rem auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card rating-card">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Rate Event: <?= htmlspecialchars($event['event_name']) ?></h2>
                <h4 class="text-center mb-4"><?= htmlspecialchars($event['organization_name']) ?></h4>
                
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="organization_rating" class="form-label">Organization Rating</label>
                        <input type="range" class="form-range" min="0" max="5" step="0.5" id="organization_rating" name="organization_rating" required>
                        <div class="text-center" id="organization_rating_value">0</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="experience_rating" class="form-label">Experience Rating</label>
                        <input type="range" class="form-range" min="0" max="5" step="0.5" id="experience_rating" name="experience_rating" required>
                        <div class="text-center" id="experience_rating_value">0</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="impact_rating" class="form-label">Impact Rating</label>
                        <input type="range" class="form-range" min="0" max="5" step="0.5" id="impact_rating" name="impact_rating" required>
                        <div class="text-center" id="impact_rating_value">0</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="support_rating" class="form-label">Support Rating</label>
                        <input type="range" class="form-range" min="0" max="5" step="0.5" id="support_rating" name="support_rating" required>
                        <div class="text-center" id="support_rating_value">0</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="team_rating" class="form-label">Team Rating</label>
                        <input type="range" class="form-range" min="0" max="5" step="0.5" id="team_rating" name="team_rating" required>
                        <div class="text-center" id="team_rating_value">0</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="testimonial" class="form-label">Testimonial</label>
                        <textarea class="form-control" id="testimonial" name="testimonial" rows="3" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Submit Rating</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('input[type="range"]').forEach(input => {
            input.addEventListener('input', function() {
                document.getElementById(this.id + '_value').textContent = this.value;
            });
        });
    </script>
</body>
</html>