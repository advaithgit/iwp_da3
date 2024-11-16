<?php
session_start();
require_once 'db_connection.php';

// Assuming volunteer_id is stored in session after login
$volunteer_id = $_SESSION['volunteer_id'] ?? 1;

if (!$volunteer_id) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Events Listing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.css">
    <style>
        .event-card {
            height: 100%;
            transition: transform 0.3s;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .carousel-control-prev, .carousel-control-next {
            width: 5%;
            background-color: rgba(0,0,0,0.2);
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        .progress {
            height: 10px;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Filters Column -->
        <div class="col-md-3">
            <div class="filter-section sticky-top">
                <h4>Filters</h4>
                <form id="filterForm">
                    <div class="mb-3">
                        <label class="form-label">Age Groups</label>
                        <?php
                        $age_groups = ['18-25', '26-35', '36-50', '50+'];
                        foreach ($age_groups as $age) {
                            echo "<div class='form-check'>
                                    <input class='form-check-input' type='checkbox' name='age_group[]' value='$age' id='age_$age'>
                                    <label class='form-check-label' for='age_$age'>$age</label>
                                  </div>";
                        }
                        ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Event Type</label>
                        <?php
                        $event_types = ['Community service', 'Awareness campaign', 'Fundraising', 'Disaster Relief', 'Workshops/Training'];
                        foreach ($event_types as $type) {
                            $type_id = str_replace(' ', '_', strtolower($type));
                            echo "<div class='form-check'>
                                    <input class='form-check-input' type='checkbox' name='event_type[]' value='$type' id='type_$type_id'>
                                    <label class='form-check-label' for='type_$type_id'>$type</label>
                                  </div>";
                        }
                        ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Skills Needed</label>
                        <?php
                        $skills = ['Technical Skills', 'General Skills', 'Managerial Skills'];
                        foreach ($skills as $skill) {
                            $skill_id = str_replace(' ', '_', strtolower($skill));
                            echo "<div class='form-check'>
                                    <input class='form-check-input' type='checkbox' name='skills[]' value='$skill' id='skill_$skill_id'>
                                    <label class='form-check-label' for='skill_$skill_id'>$skill</label>
                                  </div>";
                        }
                        ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Duration</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="duration[]" value="short" id="duration_short">
                            <label class="form-check-label" for="duration_short">Short (1 day)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="duration[]" value="medium" id="duration_medium">
                            <label class="form-check-label" for="duration_medium">Medium (1-2 days)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="duration[]" value="long" id="duration_long">
                            <label class="form-check-label" for="duration_long">Long (3+ days)</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-2">Apply Filters</button>
                    <button type="reset" class="btn btn-secondary w-100" id="resetFilters">Reset Filters</button>
                </form>
            </div>
        </div>

        <!-- Events Display Column -->
        <div class="col-md-9">
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" id="eventsTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" href="#upcoming" role="tab">Upcoming Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="registered-tab" data-bs-toggle="tab" href="#registered" role="tab">Registered Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="past-tab" data-bs-toggle="tab" href="#past" role="tab">Past Events</a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="eventsTabContent">
                <!-- Upcoming Events Tab -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                    <div id="upcomingEventsCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner" id="upcomingEventsContainer">
                            <!-- Dynamic content will be loaded here -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#upcomingEventsCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#upcomingEventsCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>

                <!-- Registered Events Tab -->
                <div class="tab-pane fade" id="registered" role="tabpanel">
                    <div id="registeredEventsCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner" id="registeredEventsContainer">
                            <!-- Dynamic content will be loaded here -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#registeredEventsCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#registeredEventsCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>

                <!-- Past Events Tab -->
                <div class="tab-pane fade" id="past" role="tabpanel">
                    <div id="pastEventsCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner" id="pastEventsContainer">
                            <!-- Dynamic content will be loaded here -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#pastEventsCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#pastEventsCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ratingForm">
                    <input type="hidden" id="event_id_rating" name="event_id">
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating</label>
                        <input type="range" class="form-range" min="1" max="5" step="1" id="rating" name="rating">
                        <div class="text-center" id="ratingValue">3</div>
                    </div>
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Testimonial</label>
                        <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Rating</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Registration Success Modal -->
<div class="modal fade" id="registrationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registration Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="registrationMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Load initial data
    loadEvents();
    
    // Filter form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        loadEvents();
    });

    // Reset filters
    $('#resetFilters').on('click', function() {
        $('#filterForm')[0].reset();
        loadEvents();
    });

    // Tab change handler
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        loadEvents();
    });

    // Rating slider value display
    $('#rating').on('input', function() {
        $('#ratingValue').text($(this).val());
    });

    // Rating form submission
    $('#ratingForm').on('submit', function(e) {
        e.preventDefault();
        submitRating();
    });
});

function loadEvents() {
    const activeTab = $('.nav-tabs .active').attr('id');
    const filterData = $('#filterForm').serialize();
    
    $.ajax({
        url: 'get_events.php',
        method: 'POST',
        data: {
            tab: activeTab,
            filters: filterData
        },
        success: function(response) {
            const data = JSON.parse(response);
            updateCarousel(data, activeTab);
        },
        error: function(xhr, status, error) {
            console.error('Error loading events:', error);
        }
    });
}

function updateCarousel(events, tabId) {
    const containerId = tabId.replace('-tab', '') + 'EventsContainer';
    const container = $(`#${containerId}`);
    container.empty();

    const itemsPerSlide = 6; // 2 rows × 3 columns
    for (let i = 0; i < events.length; i += itemsPerSlide) {
        const slideEvents = events.slice(i, i + itemsPerSlide);
        const isActive = i === 0 ? 'active' : '';
        
        let slideHtml = `
            <div class="carousel-item ${isActive}">
                <div class="container">
                    <div class="row row-cols-1 row-cols-md-3 g-4">
        `;

        slideEvents.forEach(event => {
            slideHtml += createEventCard(event, tabId);
        });

        slideHtml += `
                    </div>
                </div>
            </div>
        `;

        container.append(slideHtml);
    }

    // Reinitialize event handlers
    initializeEventHandlers();
}

function createEventCard(event, tabId) {
    const progressPercentage = (event.volunteers_registered / event.num_volunteers_needed) * 100;
    const buttonHtml = tabId === 'upcoming-tab' 
        ? `<button class="btn btn-primary register-btn" data-event-id="${event.event_id}">Register</button>`
        : tabId === 'registered-tab'
        ? `<button class="btn btn-danger unregister-btn" data-event-id="${event.event_id}">Unregister</button>`
        : `<button class="btn btn-primary rate-btn" data-event-id="${event.event_id}">Rate Event</button>`;

    return `
        <div class="col">
            <div class="card event-card h-100">
                <div class="card-body">
                    <h5 class="card-title">${event.event_name}</h5>
                    <p class="card-text">${event.event_description}</p>
                    <div class="details">
                        <p><strong>Dates:</strong> ${event.start_date} - ${event.end_date}</p>
                        <p><strong>Location:</strong> ${event.location}</p>
        <p><strong>Skills Needed:</strong> ${event.skills_needed}</p>
        <p><strong>Age Group:</strong> ${event.age_group}</p>
        ${event.perks ? `<p><strong>Perks:</strong> ${event.perks}</p>` : ''}
        <div class="progress mb-2">
            <div class="progress-bar" role="progressbar" 
                style="width: ${progressPercentage}%;" 
                aria-valuenow="${progressPercentage}" 
                aria-valuemin="0" 
                aria-valuemax="100">
                ${Math.round(progressPercentage)}%
            </div>
        </div>
        <p class="text-muted">Volunteers: ${event.volunteers_registered}/${event.num_volunteers_needed}</p>
    </div>
    ${buttonHtml}
</div>
        </div>
    `;
}

function initializeEventHandlers() {
    // Register button handler
    $('.register-btn').off('click').on('click', function() {
        const eventId = $(this).data('event-id');
        registerForEvent(eventId);
    });

    // Unregister button handler
    $('.unregister-btn').off('click').on('click', function() {
        const eventId = $(this).data('event-id');
        unregisterFromEvent(eventId);
    });

    // Rate button handler
    $('.rate-btn').off('click').on('click', function() {
        const eventId = $(this).data('event-id');
        $('#event_id_rating').val(eventId);
        $('#ratingModal').modal('show');
    });
}

function registerForEvent(eventId) {
    $.ajax({
        url: 'register_event.php',
        method: 'POST',
        data: { event_id: eventId },
        success: function(response) {
            const result = JSON.parse(response);
            $('#registrationMessage').text(result.message);
            $('#registrationModal').modal('show');
            if (result.success) {
                loadEvents(); // Refresh the events display
            }
        },
        error: function(xhr, status, error) {
            $('#registrationMessage').text('Error occurred during registration. Please try again.');
            $('#registrationModal').modal('show');
        }
    });
}

function unregisterFromEvent(eventId) {
    if (confirm('Are you sure you want to unregister from this event?')) {
        $.ajax({
            url: 'unregister_event.php',
            method: 'POST',
            data: { event_id: eventId },
            success: function(response) {
                const result = JSON.parse(response);
                $('#registrationMessage').text(result.message);
                $('#registrationModal').modal('show');
                if (result.success) {
                    loadEvents(); // Refresh the events display
                }
            },
            error: function(xhr, status, error) {
                $('#registrationMessage').text('Error occurred during unregistration. Please try again.');
                $('#registrationModal').modal('show');
            }
        });
    }
}

function submitRating() {
    const formData = $('#ratingForm').serialize();
    $.ajax({
        url: 'submit_rating.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            const result = JSON.parse(response);
            $('#ratingModal').modal('hide');
            $('#registrationMessage').text(result.message);
            $('#registrationModal').modal('show');
            if (result.success) {
                $('#ratingForm')[0].reset();
                loadEvents(); // Refresh the events display
            }
        },
        error: function(xhr, status, error) {
            $('#registrationMessage').text('Error occurred while submitting rating. Please try again.');
            $('#registrationModal').modal('show');
        }
    });
}
</script>
</body>
</html>