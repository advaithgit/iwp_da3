<?php
// Start the session
session_start();

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection details
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

// If the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Escape user inputs for security
    $name = $conn->real_escape_string($_POST['name']);
    $address = $conn->real_escape_string($_POST['address']);
    $country = $conn->real_escape_string($_POST['country']);
    $city = $conn->real_escape_string($_POST['city']);
    $state = $conn->real_escape_string($_POST['state']);
    $zip_code = $conn->real_escape_string($_POST['zip_code']);
    $phone_number = $conn->real_escape_string($_POST['phone_number']);
    $skills = $conn->real_escape_string($_POST['skills']);
    $age = $conn->real_escape_string($_POST['age']);

    // SQL query to insert data
    $sql = "INSERT INTO volunteer(name, address, country, city, state, zip_code, phone_number, skills, age) 
            VALUES ('$name', '$address', '$country', '$city', '$state', '$zip_code', '$phone_number', '$skills', '$age')";

    // Execute the query
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('New account created successfully!'); window.location.href='login.php';</script>";
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Close the connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Profile Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(90deg, rgba(73,66,189,1) 0%, rgba(19,246,249,0.78) 100%);
            font-family: Arial, sans-serif;
            color: #fff;
            padding-bottom: 50px;
        }
        .container {
            margin-top: 50px;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            color: #000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .text-center img {
            border-radius: 50%;
        }
        .form-label {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #4924ba;
            border-color: #4924ba;
        }
        .btn-primary:hover {
            background-color: #351b8f;
        }
    </style>
</head>
<body>
    <div class="container">
        <main>
            <div class="py-5 text-center">
                <img class="d-block mx-auto mb-4" src="https://via.placeholder.com/100" alt="Logo" width="100" height="100">
                <h2>Volunteer Profile</h2>
                <p class="lead">Please fill in your details to create a profile.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <form class="needs-validation" method="POST" action="volunteer_profile.php" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="invalid-feedback">Valid name is required.</div>
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" required>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" required>
                                <div class="invalid-feedback">Please enter a valid country.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                                <div class="invalid-feedback">Please enter a valid city.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" required>
                                <div class="invalid-feedback">Please provide a valid state.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="zip_code" class="form-label">Zip Code</label>
                                <input type="text" class="form-control" id="zip_code" name="zip_code" required>
                                <div class="invalid-feedback">Zip code required.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" required>
                                <div class="invalid-feedback">Age is required.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" required>
                                <div class="invalid-feedback">Please enter a valid phone number.</div>
                            </div>

                            <div class="col-12">
                                <label for="skills" class="form-label">Skills</label>
                                <input type="text" class="form-control" id="skills" name="skills" required>
                                <div class="invalid-feedback">Please enter your skills.</div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" id="volunteerAgreement" required>
                            <label class="form-check-label" for="volunteerAgreement">I agree to be a volunteer and follow the terms.</label>
                            <div class="invalid-feedback">You must agree before submitting.</div>
                        </div>

                        <button class="w-100 btn btn-primary btn-lg" type="submit">Submit</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Example starter JavaScript for disabling form submissions if there are invalid fields
    (function () {
        'use strict'

        var forms = document.querySelectorAll('.needs-validation')

        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }

                    form.classList.add('was-validated')
                }, false)
            })
    })()
    </script>
</body>
</html>
