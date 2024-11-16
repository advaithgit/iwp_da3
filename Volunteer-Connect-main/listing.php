<?php 
session_start();
// Add this at the top of your PHP file
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';
// Assume $volunteer_id is set in the session after login
$volunteer_id = $_SESSION['volunteer_id'] ?? 1; // Fallback for testing

// Function to get events based on type and filters
function getEvents($pdo, $type, $filters = []) {
    $query = "SELECT e.*, o.name as organization_name, o.id as organization_id, o.star_rating,
              CASE WHEN vr.volunteer_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
              FROM organization_events e
              JOIN organization_profile o ON e.organization_id = o.id
              LEFT JOIN volunteer_registration vr ON e.event_id = vr.event_id AND vr.volunteer_id = :volunteer_id
              WHERE 1=1";
    $params = [':volunteer_id' => $GLOBALS['volunteer_id']];

    switch ($type) {
        case 'past':
            $query .= " AND e.status = 'closed'";
            break;
        case 'registered':
            $query .= " AND e.status = 'open' AND vr.volunteer_id IS NOT NULL";
            break;
        case 'upcoming':
            $query .= " AND e.status = 'open' AND vr.volunteer_id IS NULL";
            break;
    }

    if (!empty($filters['age_group'])) {
        $ageGroups = [];
        $ageGroupParams = [];
        foreach ($filters['age_group'] as $index => $ageGroup) {
            $param = ":age_group_$index";
            $ageGroups[] = "$param";
            $ageGroupParams[$param] = $ageGroup;
        }
        $query .= " AND e.age_group IN (" . implode(',', $ageGroups) . ")";
        $params = array_merge($params, $ageGroupParams);
    }
    
    if (!empty($filters['event_type'])) {
        $query .= " AND e.type_of_event = :event_type";
        $params[':event_type'] = $filters['event_type'];
    }
    
    if (!empty($filters['skills'])) {
        $skillConditions = [];
        foreach ($filters['skills'] as $index => $skill) {
            $param = ":skill$index";
            $skillConditions[] = "e.skills_needed LIKE $param";
            $params[$param] = "%$skill%";
        }
        $query .= " AND (" . implode(" OR ", $skillConditions) . ")";
    }
    
    if (!empty($filters['duration'])) {
        $query .= " AND CASE 
                        WHEN :duration = 'short' THEN DATEDIFF(e.end_date, e.start_date) = 0
                        WHEN :duration = 'medium' THEN DATEDIFF(e.end_date, e.start_date) BETWEEN 1 AND 2
                        WHEN :duration = 'long' THEN DATEDIFF(e.end_date, e.start_date) > 2
                    END";
        $params[':duration'] = $filters['duration'];
    }

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return [];
    }
}

// Handle event registration/unregistration and rating submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_rating'])) {
        // Handle rating submission
        $event_id = $_POST['event_id'];
        $rating = $_POST['rating'];
        $testimonial = $_POST['testimonial'];

        try {
            $pdo->beginTransaction();

            // Insert or update the event_testimonials table
            $stmt = $pdo->prepare("INSERT INTO event_testimonials (event_id, volunteer_id, rating, feedback, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE rating = ?, feedback = ?, created_at = NOW()");
            $stmt->execute([$event_id, $volunteer_id, $rating, $testimonial, $rating, $testimonial]);

            // Update the average rating in the organization_events table
            $stmt = $pdo->prepare("UPDATE organization_events e
                                   SET e.star_rating = (
                                       SELECT AVG(et.rating)
                                       FROM event_testimonials et
                                       WHERE et.event_id = e.event_id
                                   )
                                   WHERE e.event_id = ?");
            $stmt->execute([$event_id]);

            // Update the average rating in the organization_profile table
            $stmt = $pdo->prepare("UPDATE organization_profile op
                                   SET op.star_rating = (
                                       SELECT AVG(e.star_rating)
                                       FROM organization_events e
                                       WHERE e.organization_id = op.id
                                   )
                                   WHERE op.id = (
                                       SELECT organization_id
                                       FROM organization_events
                                       WHERE event_id = ?
                                   )");
            $stmt->execute([$event_id]);

            $pdo->commit();
            $_SESSION['message'] = "Rating submitted successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error submitting rating. Please try again.";
            error_log("Rating submission error: " . $e->getMessage());
        }
    } else {
        // Handle event registration/unregistration
        $event_id = $_POST['event_id'];
        $action = $_POST['action'];

        if ($action == 'register') {
            $pdo->beginTransaction();
            try {
                // Check if the event is full
                $checkEventQuery = "SELECT num_volunteers_needed, volunteers_registered FROM organization_events WHERE event_id = ?";
                $checkEventStmt = $pdo->prepare($checkEventQuery);
                $checkEventStmt->execute([$event_id]);
                $eventData = $checkEventStmt->fetch(PDO::FETCH_ASSOC);

                if ($eventData['volunteers_registered'] < $eventData['num_volunteers_needed']) {
                    // Register the volunteer
                    $registerQuery = "INSERT INTO volunteer_registration (event_id, volunteer_id, registration_date) VALUES (?, ?, CURDATE())";
                    $registerStmt = $pdo->prepare($registerQuery);
                    $registerStmt->execute([$event_id, $volunteer_id]);

                    // Update the volunteers_registered count
                    $updateEventQuery = "UPDATE organization_events SET volunteers_registered = volunteers_registered + 1 WHERE event_id = ?";
                    $updateEventStmt = $pdo->prepare($updateEventQuery);
                    $updateEventStmt->execute([$event_id]);

                    $pdo->commit();
                    $_SESSION['message'] = "Successfully registered for the event.";
                } else {
                    // Event is full
                    $pdo->rollBack();
                    $_SESSION['error'] = "Sorry, this event is already full.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Registration error: " . $e->getMessage());
                $_SESSION['error'] = "An error occurred during registration. Please try again.";
            }
        } elseif ($action == 'unregister') {
            $pdo->beginTransaction();
            try {
                // Unregister the volunteer
                $unregisterQuery = "DELETE FROM volunteer_registration WHERE event_id = ? AND volunteer_id = ?";
                $unregisterStmt = $pdo->prepare($unregisterQuery);
                $unregisterStmt->execute([$event_id, $volunteer_id]);

                // Update the volunteers_registered count
                $updateEventQuery = "UPDATE organization_events SET volunteers_registered = volunteers_registered - 1 WHERE event_id = ?";
                $updateEventStmt = $pdo->prepare($updateEventQuery);
                $updateEventStmt->execute([$event_id]);

                $pdo->commit();
                $_SESSION['message'] = "Successfully unregistered from the event.";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Unregistration error: " . $e->getMessage());
                $_SESSION['error'] = "An error occurred during unregistration. Please try again.";
            }
        }
    }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }

// Get filters from GET parameters
$filters = [
    'age_group' => $_GET['age_group'] ?? [],
    'event_type' => $_GET['event_type'] ?? '',
    'skills' => $_GET['skills'] ?? [],
    'duration' => $_GET['duration'] ?? ''
];

// Get events for each section
$pastEvents = getEvents($pdo, 'past', $filters);
$registeredEvents = getEvents($pdo, 'registered', $filters);
$upcomingEvents = getEvents($pdo, 'upcoming', $filters);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Connect - Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .event-card {
            height: 100%;
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        .perks {
            background-color: #e8f5e9;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .rating-stars {
            font-size: 2em;
            color: #ffd700;
        }
    </style>
</head>
<body>
    <!-- Header remains the same -->
    <header class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Volunteer Connect</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="./index.html">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="./index.html">About</a></li>
            </ul>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://via.placeholder.com/30" alt="Profile" class="rounded-circle"> User
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="./profile.html">Profile</a></li>
                    <li><a class="dropdown-item" href="#">Settings</a></li>
                    <li><a class="dropdown-item" href="./index.html">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>
    <div class="container-fluid mt-3">
        <div class="row">
            <!-- Sidebar with filters -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <h5>Filters</h5>
                    <form id="filterForm" method="GET">
                        <div class="mb-3">
                            <label class="form-label">Age Group</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="age_group[]" value="18-25" id="age18-25" <?= in_array('18-25', $filters['age_group']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="age18-25">18-25</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="age_group[]" value="26-35" id="age26-35" <?= in_array('26-35', $filters['age_group']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="age26-35">26-35</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="age_group[]" value="36-50" id="age36-50" <?= in_array('36-50', $filters['age_group']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="age36-50">36-50</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="age_group[]" value="50+" id="age50plus" <?= in_array('50+', $filters['age_group']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="age50plus">50+</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Type of Event</label>
                            <select class="form-select" name="event_type">
                                <option value="">Choose...</option>
                                <option value="Community Service" <?= $filters['event_type'] == 'Community Service' ? 'selected' : '' ?>>Community Service</option>
                                <option value="Awareness Campaign" <?= $filters['event_type'] == 'Awareness Campaign' ? 'selected' : '' ?>>Awareness Campaign</option>
                                <option value="Fundraising" <?= $filters['event_type'] == 'Fundraising' ? 'selected' : '' ?>>Fundraising</option>
                                <option value="Workshops/ Training" <?= $filters['event_type'] == 'Workshops/ Training' ? 'selected' : '' ?>>Workshops/ Training</option>
                                <option value="Disaster Relief" <?= $filters['event_type'] == 'Disaster Relief' ? 'selected' : '' ?>>Disaster Relief</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Skills</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="skills[]" value="Technical" id="technicalSkills" <?= in_array('Technical', $filters['skills']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="technicalSkills">Technical Skills</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="skills[]" value="General" id="generalSkills" <?= in_array('General', $filters['skills']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="generalSkills">General Skills</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="skills[]" value="Managerial" id="managerialSkills" <?= in_array('Managerial', $filters['skills']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="managerialSkills">Managerial Skills</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Duration</label>
                            <select class="form-select" name="duration">
                                <option value="">Choose...</option>
                                <option value="short" <?= $filters['duration'] == 'short' ? 'selected' : '' ?>>Short-term (1 Day)</option>
                                <option value="medium" <?= $filters['duration'] == 'medium' ? 'selected' : '' ?>>Medium-term (1-2 Days)</option>
                                <option value="long" <?= $filters['duration'] == 'long' ? 'selected' : '' ?>>Long-term (2+ Days)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <button type="button" class="btn btn-secondary" id="resetFilters">Reset</button>
                    </form>
                </div>
            </nav>

            <!-- Main content area remains the same -->
            <!-- Main content area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Toast for messages -->
                <?php if (isset($_SESSION['message']) || isset($_SESSION['error'])): ?>
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
                    <div class="toast-header">
                        <strong class="me-auto">Notification</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        <?php
                        if (isset($_SESSION['message'])) {
                            echo $_SESSION['message'];
                            unset($_SESSION['message']);
                        } elseif (isset($_SESSION['error'])) {
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabs for different event sections -->
                <ul class="nav nav-tabs" id="eventTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">Upcoming Events</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="registered-tab" data-bs-toggle="tab" data-bs-target="#registered" type="button" role="tab">Registered Events</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">Past Events</button>
                    </li>
                </ul>

                <div class="tab-content" id="eventTabContent">
                    <!-- Upcoming Events -->
                    <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                        <h2 class="mt-4">Upcoming Events</h2>
                        <div class="row">
                            <?php foreach ($upcomingEvents as $event): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card event-card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <?= htmlspecialchars($event['organization_name']) ?>
                                                <span class="ms-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $event['star_rating']): ?>
                                                            ★
                                                        <?php else: ?>
                                                            ☆
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </span>
                                            </h6>
                                            <p class="card-text"><?= htmlspecialchars($event['event_description']) ?></p>
                                            <p><strong>Date:</strong> <?= htmlspecialchars($event['start_date']) ?> - <?= htmlspecialchars($event['end_date']) ?></p>
                                            <p><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
                                            <p><strong>Skills Needed:</strong> <?= htmlspecialchars($event['skills_needed']) ?></p>
                                            <p><strong>Age Group:</strong> <?= htmlspecialchars($event['age_group']) ?></p>
                                            <?php if (!empty($event['perks'])): ?>
                                                <p class="perks"><strong>Perks:</strong> <?= htmlspecialchars($event['perks']) ?></p>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                                <input type="hidden" name="action" value="register">
                                                <?php if ($event['volunteers_registered'] < $event['num_volunteers_needed']): ?>
                                                    <button type="submit" class="btn btn-primary">Register</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-secondary" disabled>Registration Full</button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Registered Events -->
                    <div class="tab-pane fade" id="registered" role="tabpanel">
                        <h2 class="mt-4">Registered Events</h2>
                        <div class="row">
                            <?php foreach ($registeredEvents as $event): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card event-card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($event['organization_name']) ?></h6>
                                            <p class="card-text"><?= htmlspecialchars($event['event_description']) ?></p>
                                            <p><strong>Date:</strong> <?= htmlspecialchars($event['start_date']) ?> - <?= htmlspecialchars($event['end_date']) ?></p>
                                            <form method="POST">
                                                <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                                <input type="hidden" name="action" value="unregister">
                                                <button type="submit" class="btn btn-warning">Unregister</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                   <!-- Past events -->
                   <div class="tab-pane fade" id="past" role="tabpanel">
                        <h2 class="mt-4">Past Events</h2>
                        <div class="row">
                            <?php foreach ($pastEvents as $event): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card event-card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($event['organization_name']) ?></h6>
                                            <p class="card-text"><?= htmlspecialchars($event['event_description']) ?></p>
                                            <p><strong>Date:</strong> <?= htmlspecialchars($event['start_date']) ?> - <?= htmlspecialchars($event['end_date']) ?></p>
                                            <?php if ($event['is_registered']): ?>
                                                <button type="button" class="btn btn-primary" onclick="openRatingModal(<?= $event['event_id'] ?>, '<?= htmlspecialchars(addslashes($event['event_name'])) ?>', '<?= htmlspecialchars(addslashes($event['organization_name'])) ?>', <?= $event['organization_id'] ?>, 'path_to_event_image/<?= htmlspecialchars($event['event_id']) ?>.jpg')">Rate Event</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Rating Modal -->
    <div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ratingModalLabel">Rate Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ratingForm" method="POST">
                        <input type="hidden" name="event_id" id="eventIdInput">
                        <div class="text-center mb-3">
                            
                        </div>
                        <h4 id="eventName" class="text-center mb-3"></h4>
                        <h5 id="organizationName" class="text-center mb-3">
                            <a href="#" id="organizationLink">Organization Name</a>
                        </h5>
                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating</label>
                            <input type="range" class="form-range" min="1" max="5" step="0.5" id="rating" name="rating">
                            <div class="text-center rating-stars" id="ratingValue"></div>
                        </div>
                        <div class="mb-3">
                            <label for="testimonial" class="form-label">Testimonial</label>
                            <textarea class="form-control" id="testimonial" name="testimonial" rows="3"></textarea>
                        </div>
                        <button type="submit" name="submit_rating" class="btn btn-primary">Submit Rating</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Function to handle rating modal
    function openRatingModal(eventId, eventName, organizationName, organizationId, eventImage) {
        document.getElementById('eventIdInput').value = eventId;
        document.getElementById('eventName').textContent = eventName;
        document.getElementById('organizationName').textContent = organizationName;
        document.getElementById('organizationLink').href = "organization_profile.php?id=" + organizationId;
        document.getElementById('eventImage').src = eventImage;

        // Reset rating value
        document.getElementById('rating').value = 1;
        updateRatingStars(1);

        var ratingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
        ratingModal.show();
    }

    // Handle range input changes
    document.getElementById('rating').addEventListener('input', function() {
        updateRatingStars(this.value);
    });

    function updateRatingStars(value) {
        const starsElement = document.getElementById('ratingValue');
        const fullStars = Math.floor(value);
        const halfStar = value % 1 !== 0;
        let starsHTML = '★'.repeat(fullStars);
        if (halfStar) {
            starsHTML += '½';
        }
        starsHTML += '☆'.repeat(5 - Math.ceil(value));
        starsElement.innerHTML = starsHTML;
    }
    // Handle range input changes
    document.querySelectorAll('input[type="range"]').forEach(input => {
        input.addEventListener('input', function() {
            document.getElementById(this.id + 'Value').textContent = this.value;
        });
    });

    // Handle filter reset
    document.getElementById('resetFilters').addEventListener('click', function() {
        // Reset checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Reset select elements
        document.querySelectorAll('select').forEach(select => {
            select.value = '';
        });
        
        // Submit the form to clear filters
        document.getElementById('filterForm').submit();
    });

    // Show toast if there's a message or error
    window.addEventListener('load', function() {
        var toastEl = document.querySelector('.toast');
        if (toastEl && toastEl.querySelector('.toast-body').textContent.trim() !== '') {
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        updateRatingStars(document.getElementById('rating').value);
    });
    </script>
</body>
</html> 10px
 10px
  
