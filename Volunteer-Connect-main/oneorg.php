<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        $org_id = 1;

        // Get profile data with average rating from testimonials
        $profile_query = "SELECT p.*, 
            COALESCE(AVG(t.rating), 0) as avg_rating
            FROM organization_profiles p
            LEFT JOIN organization_events e ON e.organization_id = p.organization_id
            LEFT JOIN event_testimonials t ON t.event_id = e.event_id
            WHERE p.organization_id = ?
            GROUP BY p.id";
        
        $stmt = $conn->prepare($profile_query);
        $stmt->bind_param("i", $org_id);
        $stmt->execute();
        $profile_result = $stmt->get_result();
        $profile_data = $profile_result->fetch_assoc();
        
        switch ($_POST['action']) {
            case 'create_event':
                $stmt = $conn->prepare("INSERT INTO organization_events (organization_id, event_name, event_description, 
                                    start_date, end_date, location, skills_needed, perks, num_volunteers_needed, 
                                    type_of_event, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')");
                
                $stmt->bind_param("isssssssss", 
                    $org_id,
                    $_POST['event_name'],
                    $_POST['event_description'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['location'],
                    $_POST['skills_needed'],
                    $_POST['perks'],
                    $_POST['num_volunteers_needed'],
                    $_POST['type_of_event']
                );
                
                echo json_encode(['success' => $stmt->execute()]);
                exit;
                
            case 'toggle_event':
                if (isset($_POST['event_id'])) {
                    $stmt = $conn->prepare("UPDATE organization_events SET status = 'closed' WHERE event_id = ?");
                    $stmt->bind_param("i", $_POST['event_id']);
                    echo json_encode(['success' => $stmt->execute()]);
                }
                exit;
                
            case 'delete_event':
                if (isset($_POST['event_id'])) {
                    $conn->begin_transaction();
                    try {
                        // Delete registrations first
                        $stmt = $conn->prepare("DELETE FROM volunteer_registration WHERE event_id = ?");
                        $stmt->bind_param("i", $_POST['event_id']);
                        $stmt->execute();
                        
                        // Delete testimonials
                        $stmt = $conn->prepare("DELETE FROM event_testimonials WHERE event_id = ?");
                        $stmt->bind_param("i", $_POST['event_id']);
                        $stmt->execute();
                        
                        // Delete event
                        $stmt = $conn->prepare("DELETE FROM organization_events WHERE event_id = ?");
                        $stmt->bind_param("i", $_POST['event_id']);
                        $stmt->execute();
                        
                        $conn->commit();
                        echo json_encode(['success' => true]);
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Database error']);
                    }
                }
                exit;
        }
    }
}

// Handle AJAX GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_volunteers' && isset($_GET['event_id'])) {
        $stmt = $conn->prepare("SELECT v.* FROM volunteer v 
                              JOIN volunteer_registration vr ON v.volunteer_id = vr.volunteer_id 
                              WHERE vr.event_id = ?");
        
        $stmt->bind_param("i", $_GET['event_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $volunteers = [];
        while ($row = $result->fetch_assoc()) {
            $volunteers[] = $row;
        }
        
        echo json_encode(['success' => true, 'volunteers' => $volunteers]);
        exit;
    }
}

// Regular page load
$org_id = 1;
$profile_query = "SELECT * FROM organization_profiles WHERE organization_id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();

// Fetch testimonials
$testimonials_query = "SELECT t.*, v.name as volunteer_name 
                      FROM event_testimonials t 
                      JOIN volunteer v ON t.volunteer_id = v.volunteer_id 
                      JOIN organization_events e ON t.event_id = e.event_id 
                      WHERE e.organization_id = ?";
$stmt = $conn->prepare($testimonials_query);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$testimonials_result = $stmt->get_result();

// Fetch past events
$past_events_query = "SELECT * FROM organization_events 
                     WHERE organization_id = ? AND status = 'closed' 
                     ORDER BY end_date DESC";
$stmt = $conn->prepare($past_events_query);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$past_events_result = $stmt->get_result();

// Fetch ongoing events
$ongoing_events_query = "SELECT oe.*, 
                        COALESCE(COUNT(DISTINCT vr.volunteer_id), 0) as volunteers_registered
                        FROM organization_events oe 
                        LEFT JOIN volunteer_registration vr ON oe.event_id = vr.event_id
                        WHERE oe.organization_id = ? AND oe.status = 'open' 
                        GROUP BY oe.event_id
                        ORDER BY oe.start_date ASC";
$stmt = $conn->prepare($ongoing_events_query);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$ongoing_events_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .profile-header {
            background-image: url('./CHILDREN.jpeg');
            background-size: cover;
            height: 300px;
            position: relative;
        }

        .profile-pic {
            position: absolute;
            bottom: -50px;
            left: 50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
        }

        .carousel .row {
            display: flex;
        }

        .card {
            display: flex;
            flex-direction: column;
            height: 100%;
            margin-bottom: 20px;
        }

        .event-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }

        .event-card:hover {
            transform: translateY(-5px);
        }

        .event-details p {
            margin-bottom: 0.5rem;
        }

        .event-details i {
            margin-right: 0.5rem;
            color: #007bff;
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .progress-bar {
            border-radius: 5px;
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 5%;
            background-color: rgba(0, 0, 0, 0.2);
        }

        .carousel-control-prev:hover,
        .carousel-control-next:hover {
            background-color: rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body>
<header class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Volunteer Connect</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="./index.html">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="./index.html">About</a></li>
            </ul>
            <div class="ms-auto">
                <a href="./index.html" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </div>
</header>

    <div class="container-fluid p-0">
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="./DBZ.jpg" alt="Profile Picture" class="profile-pic">
        </div>
        
        <!-- Profile Info -->
        <div class="container mt-5 pt-5">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>VConnect</h1>
                    <p class="lead"><?php echo htmlspecialchars($profile_data['tagline'] ?? ''); ?></p>
                </div>
                <div class="text-end">
                    <div class="star-rating">
                        <?php
                        $rating = $profile_data['star_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="bi bi-star-fill text-warning"></i>';
                            } elseif ($i - 0.5 <= $rating) {
                                echo '<i class="bi bi-star-half text-warning"></i>';
                            } else {
                                echo '<i class="bi bi-star text-warning"></i>';
                            }
                        }
                        ?>
                        <span class="ms-2"><?php echo number_format($rating, 1); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-8">
                    <h3>About</h3>
                    <p><?php echo nl2br(htmlspecialchars($profile_data['about'] ?? '')); ?></p>
                </div>
            </div>
            
            <!-- Testimonials Section -->
            <?php if ($testimonials_result->num_rows > 0): ?>
            <h3 class="mt-5">Testimonials</h3>
            <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php 
                    $counter = 0;
                    while ($testimonials = $testimonials_result->fetch_assoc()): 
                        if ($counter % 3 == 0):
                    ?>
                        <div class="carousel-item <?php echo $counter == 0 ? 'active' : ''; ?>">
                            <div class="row">
                    <?php endif; ?>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="star-rating mb-2">
                                                <?php
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $testimonials['rating'] 
                                                        ? '<i class="bi bi-star-fill text-warning"></i>' 
                                                        : '<i class="bi bi-star text-warning"></i>';
                                                }
                                                ?>
                                            </div>
                                            <p class="card-text">"<?php echo htmlspecialchars($testimonials['feedback']); ?>"</p>
                                            <footer class="blockquote-footer"><?php echo htmlspecialchars($testimonials['volunteer_name']); ?></footer>
                                        </div>
                                    </div>
                                </div>
                    <?php 
                        if (($counter + 1) % 3 == 0 || $counter == $testimonials_result->num_rows - 1):
                    ?>
                            </div>
                        </div>
                    <?php 
                        endif;
                        $counter++;
                    endwhile; 
                    ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Past Events Section -->
            <?php if ($past_events_result->num_rows > 0): ?>
            <h3 class="mt-5">Past Events</h3>
            <div id="pastEventCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php 
                    $counter = 0;
                    while ($past_event = $past_events_result->fetch_assoc()): 
                        if ($counter % 3 == 0):
                    ?>
                        <div class="carousel-item <?php echo $counter == 0 ? 'active' : ''; ?>">
                            <div class="row">
                    <?php endif; ?>
                                <div class="col-md-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($past_event['event_name']); ?></h5>
                                            <p class="card-text">
                                                <strong>Type:</strong> <?php echo htmlspecialchars($past_event['type_of_event']); ?><br>
                                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($past_event['start_date'])); ?>
                                            </p>
                                            <p class="card-text"><?php echo htmlspecialchars($past_event['event_description']); ?></p>
                                        </div>
                                    </div>
                                </div>
                    <?php 
                        if (($counter + 1) % 3 == 0 || $counter == $past_events_result->num_rows - 1):
                    ?>
                            </div>
                        </div>
                    <?php 
                        endif;
                        $counter++;
                    endwhile; 
                    ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#pastEventCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#pastEventCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Ongoing Events Section -->
            <h3 class="mt-5">Ongoing Events</h3>
            <div id="ongoingEventCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php 
                    if ($ongoing_events_result->num_rows > 0):
                        $counter = 0;
                        while ($ongoing_event = $ongoing_events_result->fetch_assoc()): 
                            if ($counter % 3 == 0):
                    ?>
                        <div class="carousel-item <?php echo $counter == 0 ? 'active' : ''; ?>">
                            <div class="row">
                    <?php endif; ?>
                                <div class="col-md-4">
                                    <div class="card event-card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($ongoing_event['event_name']); ?></h5>
                                            <p class="card-text"><?php echo htmlspecialchars($ongoing_event['event_description']); ?></p>
                                            <div class="event-details">
                                                <p><i class="bi bi-calendar-event"></i> Start: <?php echo date('M d, Y', strtotime($ongoing_event['start_date'])); ?></p>
                                                <p><i class="bi bi-calendar-check"></i> End: <?php echo date('M d, Y', strtotime($ongoing_event['end_date'])); ?></p>
                                                <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ongoing_event['location']); ?></p>
                                                <p><i class="bi bi-people-fill"></i> 
                                                    <a href="#" class="view-volunteers" data-event-id="<?php echo $ongoing_event['event_id']; ?>">
                                                        <?php echo $ongoing_event['volunteers_registered']; ?> Volunteers Registered
                                                    </a>
                                                </p>
                                            </div>
                                            <?php
                                            $progress = ($ongoing_event['volunteers_registered'] / $ongoing_event['num_volunteers_needed']) * 100;
                                            ?>
                                            <div class="progress mt-3">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%;" 
                                                     aria-valuenow="<?php echo $progress; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo round($progress); ?>% Filled
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <button class="btn btn-warning btn-sm toggle-event" 
                                                        data-event-id="<?php echo $ongoing_event['event_id']; ?>">
                                                    Close Registration
                                                </button>
                                                <button class="btn btn-danger btn-sm delete-event" 
                                                        data-event-id="<?php echo $ongoing_event['event_id']; ?>">
                                                    Delete Event
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                    <?php 
                        if (($counter + 1) % 3 == 0 || $counter == $ongoing_events_result->num_rows - 1):
                    ?>
                            </div>
                        </div>
                    <?php 
                        endif;
                        $counter++;
                        endwhile;
                    else:
                    ?>
                        <div class="alert alert-info">No ongoing events at the moment.</div>
                    <?php endif; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#ongoingEventCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#ongoingEventCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>

            <!-- New Event Form -->
            <h3 class="mt-5">Post New Event</h3>
            <form id="newEventForm" class="mb-5">
                <div class="mb-3">
                    <label for="eventName" class="form-label">Event Name</label>
                    <input type="text" class="form-control" id="eventName" name="event_name" required>
                </div>
                <div class="mb-3">
                    <label for="eventDescription" class="form-label">Event Description</label>
                    <textarea class="form-control" id="eventDescription" name="event_description" rows="3" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" name="start_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" name="end_date" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location" required>
                </div>
                <div class="mb-3">
                    <label for="volunteersNeeded" class="form-label">Number of Volunteers Needed</label>
                    <input type="range" class="form-range" min="1" max="100" id="volunteersNeeded" name="num_volunteers_needed">
                    <output for="volunteersNeeded" id="volunteersOutput">50</output>
                </div>
                <div class="mb-3">
                    <label for="skillsNeeded" class="form-label">Skills Needed</label>
                    <select class="form-select" id="skillsNeeded" name="skills_needed" required>
                        <option value="" selected disabled>Choose...</option>
                        <option value="Technical Skills">Technical Skills</option>
                        <option value="Managerial Skills">Managerial Skills</option>
                        <option value="General Skills">General Skills</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="eventType" class="form-label">Type of Event</label>
                    <select class="form-select" id="eventType" name="type_of_event" required>
                        <option value="" selected disabled>Choose...</option>
                        <option value="Community service">Community Service</option>
                        <option value="Awareness campaign">Awareness Campaign</option>
                        <option value="Fundraising">Fundraising</option>
                        <option value="Disaster Relief">Disaster Relief</option>
                        <option value="Workshops/Training">Workshops/Training</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="perks" class="form-label">Perks (Optional)</label>
                    <textarea class="form-control" id="perks" name="perks" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Event</button>
            </form>
        </div>
    </div>

    <!-- Volunteers Modal -->
    <div class="modal fade" id="volunteersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registered Volunteers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Skills</th>
                                    <th>Phone Number</th>
                                </tr>
                            </thead>
                            <tbody id="volunteersTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/org-profile.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Update volunteers needed output
            $('#volunteersNeeded').on('input', function() {
                $('#volunteersOutput').text(this.value);
            });

            // Form submission for new event
            $('#newEventForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: $(this).serialize() + '&action=create_event',
                    success: function(response) {
                        if (response.success) {
                            alert('Event created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error creating event. Please try again.');
                    }
                });
            });

            // View volunteers for an event
            $('.view-volunteers').on('click', function(e) {
                e.preventDefault();
                const eventId = $(this).data('event-id');
                
                $.ajax({
                    url: window.location.href,
                    method: 'GET',
                    data: { action: 'get_volunteers', event_id: eventId },
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            response.volunteers.forEach(function(volunteer) {
                                html += `
                                    <tr>
                                        <td>${volunteer.name}</td>
                                        <td>${volunteer.age}</td>
                                        <td>${volunteer.skills}</td>
                                        <td>${volunteer.phone_number}</td>
                                    </tr>
                                `;
                            });
                            $('#volunteersTableBody').html(html);
                            $('#volunteersModal').modal('show');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error fetching volunteers. Please try again.');
                    }
                });
            });

            // Toggle event status
            $('.toggle-event').on('click', function() {
                const eventId = $(this).data('event-id');
                
                if (confirm('Are you sure you want to close registrations for this event?')) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { action: 'toggle_event', event_id: eventId },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Error updating event status. Please try again.');
                        }
                    });
                }
            });

            // Delete event
            $('.delete-event').on('click', function() {
                const eventId = $(this).data('event-id');
                
                if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { action: 'delete_event', event_id: eventId },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Error deleting event. Please try again.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>