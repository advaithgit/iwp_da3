<?php
// Include database connection file
include('db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the POST request
    $event_id = $_POST['event_id'];
    $volunteer_id = $_POST['volunteer_id'];

    // Check if the volunteer is already registered for this event
    $checkQuery = "SELECT * FROM volunteer_registrations WHERE event_id = ? AND volunteer_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $event_id, $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Already registered for this event."]);
    } else {
        // Insert the registration data into the database
        $insertQuery = "INSERT INTO volunteer_registrations (event_id, volunteer_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ii", $event_id, $volunteer_id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Registered successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Registration failed."]);
        }
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>
