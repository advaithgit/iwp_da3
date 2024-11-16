<?php
// Database configuration
$servername = "localhost";
$username = "root";  // Your XAMPP MySQL username (default is root)
$password = "";      // Your XAMPP MySQL password (default is blank)
$dbname = "volunteer-connect-one-org";

// Create connection
$conn = null;
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Set charset to handle special characters properly
if ($conn) {
    $conn->set_charset("utf8mb4");    
}

// Add this to your db_connection.php to help with counting volunteers
function getVolunteersRegistered($conn, $event_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM volunteer_registration WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Helper function to check if volunteer is registered
function isVolunteerRegistered($conn, $volunteer_id, $event_id) {
    $stmt = $conn->prepare("SELECT 1 FROM volunteer_registration WHERE volunteer_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $volunteer_id, $event_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Add this error handler to your db_connection.php
function handleDatabaseError($conn, $error) {
    error_log("Database Error: " . $error);
    return [
        'success' => false,
        'message' => 'A database error occurred. Please try again later.',
    ];
}

// Add these validation functions to ensure data integrity
function validateEventData($event_id, $conn) {
    $stmt = $conn->prepare("SELECT status, num_volunteers_needed, volunteers_registered 
                           FROM organization_events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Event not found');
    }
    
    $event = $result->fetch_assoc();
    if ($event['status'] !== 'open') {
        throw new Exception('Event is not open for registration');
    }
    
    if ($event['volunteers_registered'] >= $event['num_volunteers_needed']) {
        throw new Exception('Event is already full');
    }
    
    return $event;
}

function validateVolunteerData($volunteer_id, $conn) {
    $stmt = $conn->prepare("SELECT 1 FROM volunteer WHERE volunteer_id = ?");
    $stmt->bind_param("i", $volunteer_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Volunteer not found');
    }
}

// Add these functions to help with event filtering
function buildFilterQuery($filters) {
    $where = [];
    $params = [];
    $types = "";
    
    if (!empty($filters['age_group'])) {
        $placeholders = rtrim(str_repeat('?,', count($filters['age_group'])), ',');
        $where[] = "age_group IN ($placeholders)";
        $params = array_merge($params, $filters['age_group']);
        $types .= str_repeat('s', count($filters['age_group']));
    }
    
    if (!empty($filters['event_type'])) {
        $placeholders = rtrim(str_repeat('?,', count($filters['event_type'])), ',');
        $where[] = "type_of_event IN ($placeholders)";
        $params = array_merge($params, $filters['event_type']);
        $types .= str_repeat('s', count($filters['event_type']));
    }
    
    if (!empty($filters['skills'])) {
        $placeholders = rtrim(str_repeat('?,', count($filters['skills'])), ',');
        $where[] = "skills_needed IN ($placeholders)";
        $params = array_merge($params, $filters['skills']);
        $types .= str_repeat('s', count($filters['skills']));
    }
    
    if (!empty($filters['duration'])) {
        $duration_conditions = [];
        foreach ($filters['duration'] as $duration) {
            switch ($duration) {
                case 'short':
                    $duration_conditions[] = "DATEDIFF(end_date, start_date) = 0";
                    break;
                case 'medium':
                    $duration_conditions[] = "DATEDIFF(end_date, start_date) BETWEEN 1 AND 2";
                    break;
                case 'long':
                    $duration_conditions[] = "DATEDIFF(end_date, start_date) > 2";
                    break;
            }
        }
        if (!empty($duration_conditions)) {
            $where[] = "(" . implode(" OR ", $duration_conditions) . ")";
        }
    }
    
    return [
        'where' => !empty($where) ? "AND " . implode(" AND ", $where) : "",
        'params' => $params,
        'types' => $types
    ];
}

// Add this utility class to handle responses
class ApiResponse {
    public static function success($data = null, $message = 'Success') {
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    public static function error($message = 'Error', $code = 400) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
}

// Add this function to format event data for frontend
function formatEventData($event) {
    return [
        'event_id' => $event['event_id'],
        'event_name' => $event['event_name'],
        'event_description' => $event['event_description'],
        'start_date' => date('M d, Y', strtotime($event['start_date'])),
        'end_date' => date('M d, Y', strtotime($event['end_date'])),
        'location' => $event['location'],
        'skills_needed' => $event['skills_needed'],
        'perks' => $event['perks'],
        'age_group' => $event['age_group'],
        'num_volunteers_needed' => $event['num_volunteers_needed'],
        'volunteers_registered' => $event['volunteers_registered'],
        'status' => $event['status'],
        'type_of_event' => $event['type_of_event'],
        'progress_percentage' => ($event['volunteers_registered'] / $event['num_volunteers_needed']) * 100,
        'duration' => calculateDuration($event['start_date'], $event['end_date'])
    ];
}

function calculateDuration($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $diff = $start->diff($end);
    
    if ($diff->days == 0) return 'short';
    if ($diff->days <= 2) return 'medium';
    return 'long';
}

// Add this security class to handle input sanitization
class Security {
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    public static function validateInteger($value, $min = null, $max = null) {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) return false;
        if ($min !== null && $value < $min) return false;
        if ($max !== null && $value > $max) return false;
        return true;
    }
    
    public static function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// Add error logging function
function logError($message, $context = []) {
    $log_file = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? json_encode($context) : '';
    $log_message = "[$timestamp] $message $context_str\n";
    error_log($log_message, 3, $log_file);
}
?>