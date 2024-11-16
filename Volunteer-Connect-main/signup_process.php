<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$dbname = 'volunteer-connect-one-org';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Getting data from the form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password']; // Do not hash for admin validation here

    // Check if the entered credentials are for the admin account
    if ($email === "admin@vconnect.in" && $password === "admin123") {
        // Redirect to oneorg.php for admin
        echo "<script>
            alert('Welcome, Admin!');
            window.location.href = 'oneorg.php';
        </script>";
    } else {
        // For non-admin users
        $pass = password_hash($password, PASSWORD_DEFAULT); // Hashing the password

        // Determine the appropriate table based on email domain
        $domain = substr(strrchr($email, "@"), 1);
        $isOrganization = preg_match('/(edu|ac\.[a-z]{2,3})$/i', $domain);
        $table = $isOrganization ? 'organization_signup' : 'volunteer_signup';

        // Check if the email already exists in the database
        $checkUser = "SELECT * FROM `$table` WHERE email = ?";
        $stmt = $conn->prepare($checkUser);
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            die("Error executing statement: " . $stmt->error);
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // If the email exists, show pop-up and redirect to login
            echo "<script>
                alert('Account already exists. Please log in.');
                window.location.href = 'login.php';
            </script>";
        } else {
            // If email doesn't exist, insert the new record
            $sql = "INSERT INTO `$table` (email, password) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Error preparing insert statement: " . $conn->error);
            }
            $stmt->bind_param("ss", $email, $pass);

            if ($stmt->execute()) {
                // On success, show pop-up and redirect to the appropriate profile page
                $profilePage = $isOrganization ? 'organization_profile.php' : 'volunteer_profile.php';
                echo "<script>
                    alert('New account created successfully');
                    window.location.href = '$profilePage';
                </script>";
            } else {
                echo "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>
