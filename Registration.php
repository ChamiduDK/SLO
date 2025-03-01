<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

// Function to sanitize input
function sanitize($data, $conn) {
    return htmlspecialchars(strip_tags(trim(mysqli_real_escape_string($conn, $data))));
}

// Initialize variables
$errors = [];
$data = [
    'first_name' => '', 'last_name' => '', 'username' => '', 'email' => '',
    'password' => '', 'confirm_password' => '', 'dob' => '', 'gender' => '',
    'phone_number' => '', 'address' => '', 'nic_number' => '', 'about' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $fields = array_keys($data);
    foreach ($fields as $field) {
        $data[$field] = isset($_POST[$field]) ? sanitize($_POST[$field], $connection) : '';
    }
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['step-2'] = "Invalid email format.";
    }
    if ($data['password'] !== $data['confirm_password']) {
        $errors['step-2'] = "Passwords do not match.";
    }
    if (strlen($data['password']) < 8) {
        $errors['step-2'] = "Password must be at least 8 characters long.";
    }
    if (!preg_match("/^[0-9]{10}$/", $data['phone_number'])) {
        $errors['step-3'] = "Invalid phone number format (must be 10 digits).";
    }

    // Handle file uploads
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        $errors['step-3'] = "Failed to create upload directory.";
    } elseif (!is_writable($upload_dir)) {
        $errors['step-3'] = "Upload directory is not writable.";
    }

    $nic_front = '';
    $nic_back = '';

    if (isset($_FILES['nic_front']) && $_FILES['nic_front']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['nic_front']['name'], PATHINFO_EXTENSION));
        $nic_front = $upload_dir . uniqid() . '.' . $file_ext;
        if (!move_uploaded_file($_FILES['nic_front']['tmp_name'], $nic_front)) {
            $errors['step-3'] = "Failed to upload NIC front image.";
        }
    } elseif (!isset($_FILES['nic_front']) || $_FILES['nic_front']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['step-3'] = "NIC front image is required.";
    }

    if (isset($_FILES['nic_back']) && $_FILES['nic_back']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['nic_back']['name'], PATHINFO_EXTENSION));
        $nic_back = $upload_dir . uniqid() . '.' . $file_ext;
        if (!move_uploaded_file($_FILES['nic_back']['tmp_name'], $nic_back)) {
            $errors['step-3'] = "Failed to upload NIC back image.";
        }
    } elseif (!isset($_FILES['nic_back']) || $_FILES['nic_back']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['step-3'] = "NIC back image is required.";
    }

    // If no validation errors, save to database
    if (empty($errors)) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $connection->prepare(
            "INSERT INTO users (first_name, last_name, username, email, password, dob, gender, phone_number, address, nic_number, nic_front, nic_back, about) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt === false) {
            $errors['step-3'] = "Database prepare failed: " . $connection->error;
        } else {
            $stmt->bind_param(
                "sssssssssssss",
                $data['first_name'], $data['last_name'], $data['username'], $email, $hashed_password,
                $data['dob'], $data['gender'], $data['phone_number'], $data['address'], $data['nic_number'],
                $nic_front, $nic_back, $data['about']
            );

            try {
                if ($stmt->execute()) {
                    header("Location: login.php");
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                if ($connection->errno === 1062) { // Duplicate entry
                    $error_detail = $connection->error;
                    if (strpos($error_detail, 'nic_number') !== false) {
                        $errors['step-3'] = "The NIC number '{$data['nic_number']}' is already registered.";
                    } elseif (strpos($error_detail, 'username') !== false) {
                        $errors['step-1'] = "The username '{$data['username']}' is already taken.";
                    } elseif (strpos($error_detail, 'email') !== false) {
                        $errors['step-2'] = "The email '{$data['email']}' is already registered.";
                    } else {
                        $errors['step-3'] = "A duplicate entry error occurred.";
                    }
                } else {
                    $errors['step-3'] = "Database error: " . $e->getMessage();
                }
            }
            $stmt->close();
        }
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thambapanni Heritage - Create Account</title>
    <link rel="stylesheet" href="css/reg-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .error-message {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #ffe6e6;
            border: 1px solid #ff9999;
            border-radius: 5px;
            display: block;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="image-container">
                <img src="images/download.jpg" alt="Sri Lankan Flag" class="form-image">
            </div>

            <div class="progress-bar">
                <div class="step active" data-step="1">1</div>
                <div class="step" data-step="2">2</div>
                <div class="step" data-step="3">3</div>
            </div>

            <form id="registrationForm" action="Registration.php" method="POST" enctype="multipart/form-data">
                <!-- Step 1 -->
                <div class="form-step <?php echo empty($errors) || !isset($errors['step-2']) && !isset($errors['step-3']) ? 'active' : ''; ?>" id="step-1">
                    <h2>Create Your Account</h2>
                    <p>It’s free and only takes a minute!</p>
                    <?php if (isset($errors['step-1'])) { ?>
                        <div class="error-message"><?php echo $errors['step-1']; ?></div>
                    <?php } ?>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="Enter your first name" value="<?php echo htmlspecialchars($data['first_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="Enter your last name" value="<?php echo htmlspecialchars($data['last_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="Choose a username" value="<?php echo htmlspecialchars($data['username']); ?>">
                    </div>
                    <button type="button" class="next-btn">Next <i class="fas fa-arrow-right"></i></button>
                </div>

                <!-- Step 2 -->
                <div class="form-step <?php echo isset($errors['step-2']) ? 'active' : ''; ?>" id="step-2">
                    <h2>Personal Details</h2>
                    <?php if (isset($errors['step-2'])) { ?>
                        <div class="error-message"><?php echo $errors['step-2']; ?></div>
                    <?php } ?>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($data['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Create a password" value="<?php echo htmlspecialchars($data['password']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required placeholder="Confirm your password" value="<?php echo htmlspecialchars($data['confirm_password']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" required value="<?php echo htmlspecialchars($data['dob']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="" disabled <?php echo empty($data['gender']) ? 'selected' : ''; ?>>Select your gender</option>
                            <option value="Male" <?php echo $data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $data['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <button type="button" class="prev-btn"><i class="fas fa-arrow-left"></i> Previous</button>
                    <button type="button" class="next-btn">Next <i class="fas fa-arrow-right"></i></button>
                </div>

                <!-- Step 3 -->
                <div class="form-step <?php echo isset($errors['step-3']) ? 'active' : ''; ?>" id="step-3">
                    <h2>Additional Information</h2>
                    <?php if (isset($errors['step-3'])) { ?>
                        <div class="error-message"><?php echo $errors['step-3']; ?></div>
                    <?php } ?>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone_number" required placeholder="Enter your phone number" value="<?php echo htmlspecialchars($data['phone_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" required placeholder="Enter your address"><?php echo htmlspecialchars($data['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>NIC Number</label>
                        <input type="text" name="nic_number" required placeholder="Enter your NIC number" value="<?php echo htmlspecialchars($data['nic_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label>NIC Front Image</label>
                        <input type="file" name="nic_front" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label>NIC Back Image</label>
                        <input type="file" name="nic_back" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label>About</label>
                        <textarea name="about" required placeholder="Tell us about yourself"><?php echo htmlspecialchars($data['about']); ?></textarea>
                    </div>
                    <button type="button" class="prev-btn"><i class="fas fa-arrow-left"></i> Previous</button>
                    <button type="submit" class="submit-btn">Submit <i class="fas fa-check"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const steps = document.querySelectorAll('.form-step');
        const progressSteps = document.querySelectorAll('.step');
        const nextButtons = document.querySelectorAll('.next-btn');
        const prevButtons = document.querySelectorAll('.prev-btn');
        let currentStep = <?php echo isset($errors['step-3']) ? 2 : (isset($errors['step-2']) ? 1 : 0); ?>;

        function updateSteps() {
            steps.forEach((step, index) => {
                step.classList.toggle('active', index === currentStep);
                progressSteps[index].classList.toggle('active', index <= currentStep);
            });
        }

        nextButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (currentStep < steps.length - 1) {
                    const inputs = steps[currentStep].querySelectorAll('input[required], select[required], textarea[required]');
                    let valid = true;
                    inputs.forEach(input => {
                        if (!input.value) {
                            valid = false;
                            input.reportValidity();
                        }
                    });
                    if (valid) {
                        currentStep++;
                        updateSteps();
                    }
                }
            });
        });

        prevButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (currentStep > 0) {
                    currentStep--;
                    updateSteps();
                }
            });
        });

        updateSteps(); // Initial setup
    </script>
</body>
</html>