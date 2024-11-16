<?php
session_start();
include('db_connection.php');

// Assume $volunteer_id is set in the session after login
$volunteer_id = $_SESSION['volunteer_id'] ?? 1; // Fallback for testing

// Fetch past events
$eventsQuery = "SELECT e.*, o.name as organization_name,
                CASE WHEN vr.volunteer_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
                FROM organization_events e
                JOIN organization_profile o ON e.organization_id = o.id
                LEFT JOIN volunteer_registration vr ON e.event_id = vr.event_id AND vr.volunteer_id = :volunteer_id
                WHERE e.status = 'closed'";
$eventsStmt = $pdo->prepare($eventsQuery);
$eventsStmt->execute([':volunteer_id' => $volunteer_id]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle rating submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_rating'])) {
    $event_id = $_POST['event_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];

    $ratingQuery = "INSERT INTO event_testimonials (event_id, volunteer_id, rating, feedback) 
                    VALUES (:event_id, :volunteer_id, :rating, :feedback)";
    $ratingStmt = $pdo->prepare($ratingQuery);
    $ratingStmt->execute([
        ':event_id' => $event_id,
        ':volunteer_id' => $volunteer_id,
        ':rating' => $rating,
        ':feedback' => $comments
    ]);

    // Update the organization's average rating
    $updateOrgRatingQuery = "UPDATE organization_profile op
                             SET star_rating = (
                                 SELECT AVG(et.rating)
                                 FROM event_testimonials et
                                 JOIN organization_events oe ON et.event_id = oe.event_id
                                 WHERE oe.organization_id = op.id
                             )
                             WHERE op.id = (SELECT organization_id FROM organization_events WHERE event_id = :event_id)";
    $updateOrgRatingStmt = $pdo->prepare($updateOrgRatingQuery);
    $updateOrgRatingStmt->execute([':event_id' => $event_id]);

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Connect - Past Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include('header.php'); ?>

    <div class="container mt-4">
        <h2>Past Events</h2>
        <div class="row">
            <?php foreach ($events as $event): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <img src="path_to_event_image/<?= $event['event_id'] ?>.jpg" class="card-img-top" alt="Event Image">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($event['organization_name']) ?></h6>
                            <p class="card-text"><?= htmlspecialchars($event['event_description']) ?></p>
                            <p>Date: <?= htmlspecialchars($event['start_date']) ?> - <?= htmlspecialchars($event['end_date']) ?></p>
                            <?php if ($event['is_registered']): ?>
                                <button class="btn btn-primary" onclick="openRatingModal(<?= $event['event_id'] ?>)">Rate Event</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Rating Modal -->
    <div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ratingModalLabel">Rate Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ratingForm" method="POST">
                        <input type="hidden" name="event_id" id="eventIdInput">
                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating</label>
                            <input type="range" class="form-range" min="1" max="5" step="1" id="rating" name="rating">
                            <div class="text-center" id="ratingValue">3</div>
                        </div>
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                        </div>
                        <button type="submit" name="submit_rating" class="btn btn-primary">Submit Rating</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openRatingModal(eventId) {
            document.getElementById('eventIdInput').value = eventId;
            var ratingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
            ratingModal.show();
        }

        document.getElementById('rating').addEventListener('input', function() {
            document.getElementById('ratingValue').textContent = this.value;
        });
    </script>
</body>
</html>