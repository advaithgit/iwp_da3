<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Database connection
$host = 'localhost';
$dbname = 'volunteer-connect-one-org';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $tagline = $conn->real_escape_string($_POST['tagline']);
    $about = $conn->real_escape_string($_POST['about']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $country = $conn->real_escape_string($_POST['country']);
    $city = $conn->real_escape_string($_POST['city']);
    $state = $conn->real_escape_string($_POST['state']);
    $zip_code = $conn->real_escape_string($_POST['zip_code']);
    $phone_number = $conn->real_escape_string($_POST['phone_number']);
    $profile_image = $conn->real_escape_string($_POST['profile_image']);
    
    // Insert data into the organization_profile table
    $sql = "INSERT INTO organization_profile (name, tagline, about, email, address, country, city, state, zip_code, phone_number, profile_image, followers_count, star_rating)
            VALUES ('$name', '$tagline', '$about', '$email', '$address', '$country', '$city', '$state', '$zip_code', '$phone_number', '$profile_image', 0, 0.0)";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Organization profile created successfully!'); window.location.href='login.php';</script>";
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization Profile Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(90deg, rgba(73,66,189,1) 0%, rgba(19,246,249,0.78) 100%);
            padding-bottom: 50px;
        }
    </style>
</head>
<body>
    <div class="container">
        <main>
            <div class="py-5 text-center">
                <img class="d-block mx-auto mb-4" src="https://avatars.githubusercontent.com/u/91388754?v=4" alt="" width="100" height="100">
                <h2>Organization Profile</h2>
                <p class="lead">Enter Your Organization Details</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-8">
                    <form class="needs-validation" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Organization Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="invalid-feedback">Valid organization name is required.</div>
                            </div>

                            <div class="col-12">
                                <label for="tagline" class="form-label">Tagline</label>
                                <input type="text" class="form-control" id="tagline" name="tagline" required>
                                <div class="invalid-feedback">Please enter a tagline.</div>
                            </div>

                            <div class="col-12">
                                <label for="about" class="form-label">About</label>
                                <textarea class="form-control" id="about" name="about" rows="3" required></textarea>
                                <div class="invalid-feedback">Please provide information about your organization.</div>
                            </div>

                            <div class="col-12">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" required>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>

                            <div class="col-md-5">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" required>
                                <div class="invalid-feedback">Please enter a valid country.</div>
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                                <div class="invalid-feedback">Please provide a valid city.</div>
                            </div>

                            <div class="col-md-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" required>
                                <div class="invalid-feedback">Please provide a valid state.</div>
                            </div>

                            <div class="col-md-3">
                                <label for="zip_code" class="form-label">Zip Code</label>
                                <input type="text" class="form-control" id="zip_code" name="zip_code" required>
                                <div class="invalid-feedback">Zip code required.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" required>
                                <div class="invalid-feedback">Please enter a valid phone number.</div>
                            </div>

                            <div class="col-12">
                                <label for="profile_image" class="form-label">Profile Image URL</label>
                                <input type="url" class="form-control" id="profile_image" name="profile_image"> <!-- Removed required attribute -->
                                <div class="invalid-feedback">Please enter a valid URL for the profile image.</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" id="organizationAgreement" required>
                            <label class="form-check-label" for="organizationAgreement">I agree to the terms and conditions</label>
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

      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      var forms = document.querySelectorAll('.needs-validation')

      // Loop over them and prevent submission
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