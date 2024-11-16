<?php
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

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Hardcoded admin credentials
    $admin_email = "admin@vconnect.in";
    $admin_password = "admin123";

    // Check if the email is admin
    if ($email === $admin_email && $password === $admin_password) {
        // Redirect to admin page
        echo "<script>
            alert('Login successful as Admin');
            window.location.href = 'oneorg.php';
        </script>";
        exit();
    }

    // Check in the volunteer_signup table first
    $sql = "SELECT * FROM volunteer_signup WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Fetch the row and verify the password for volunteer
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            // Redirect to volunteer listing page
            echo "<script>
                alert('Login successful as Volunteer');
                window.location.href = 'listing.php';
            </script>";
        } else {
            // Incorrect password for volunteer
            $error_message = "Incorrect password";
        }
    } else {
        // If not found in volunteer_signup, check the organization_signup table
        $sql = "SELECT * FROM organization_signup WHERE email='$email'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            // Fetch the row and verify the password for organization
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Redirect to organization profile page
                echo "<script>
                    alert('Login successful as Organization');
                    window.location.href = 'organizationprofile_org.php';
                </script>";
            } else {
                // Incorrect password for organization
                $error_message = "Incorrect password";
            }
        } else {
            // Account doesn't exist in both tables
            $error_message = "Account doesn't exist. Please sign up.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Connect - Log In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }
        
        .log-in-box {
            background-color: #f9f9f9;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 360px;
            width: 100%;
        }
        
        .logo {
            font-size: 48px;
            color: #00008B;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 30px;
        }
        
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .input-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: left;
            color: #555;
        }
        
        .input-group input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            width: calc(100% - 40px);
            padding-right: 40px;
        }
        
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
        }
        
        .toggle-password i {
            font-size: 18px;
            color: #888;
        }
        
        .toggle-password i:hover {
            color: #555;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            margin-right: 8px;
        }
        
        .checkbox-group label {
            color: #333;
        }
        
        .log-in-btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .log-in-btn:hover {
            background-color: #45a049;
        }
        
        .error-message {
            color: red;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="log-in-box">
            <h1 class="logo">VC</h1>
            <h2>Log In</h2>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" class="form-control" required>
                        <span id="togglePassword" class="toggle-password">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="rememberMe">
                    <label for="rememberMe">Remember me</label>
                </div>
                <button type="submit" class="log-in-btn">Log In</button>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordField = document.querySelector('input[name="password"]');
            const icon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
