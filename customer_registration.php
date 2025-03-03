<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

function sanitize($data, $conn) {
    return htmlspecialchars(strip_tags(trim(mysqli_real_escape_string($conn, $data))));
}

$errors = [];
$data = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'password' => '', 'confirm_password' => '', 'phone_number' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => &$value) {
        $value = isset($_POST[$key]) ? sanitize($_POST[$key], $connection) : '';
    }
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($data['password']) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match("/^[0-9]{10}$/", $data['phone_number'])) {
        $errors[] = "Invalid phone number format (10 digits).";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $connection->prepare("INSERT INTO customers (first_name, last_name, username, email, password, phone_number, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssss", $data['first_name'], $data['last_name'], $data['username'], $email, $hashed_password, $data['phone_number'], $data['address']);
            try {
                if ($stmt->execute()) {
                    header("Location: customer_login.php");
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                if ($connection->errno === 1062) {
                    $errors[] = "Username or email already taken.";
                } else {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
            $stmt->close();
        } else {
            $errors[] = "Prepare failed: " . $connection->error;
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
    <title>Thambapanni Heritage - Customer Registration</title>
    <link rel="stylesheet" href="css/reg-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
   
</head>
<body>
    <div class="container">
        <h2>Customer Registration</h2>
        <?php if (!empty($errors)) { echo '<div class="error-message">' . implode('<br>', $errors) . '</div>'; } ?>
        
        <div class="progress-bar">
            <div class="step active">1</div>
            <div class="step">2</div>
            <div class="step">3</div>
        </div>

        <form action="customer_registration.php" method="POST">
            <!-- Step 1: Personal Info -->
            <div class="form-step active">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required value="<?php echo htmlspecialchars($data['first_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required value="<?php echo htmlspecialchars($data['last_name']); ?>">
                </div>
                <button type="button" class="next-btn">Next <i class="fas fa-arrow-right"></i></button>
            </div>

            <!-- Step 2: Account Info -->
            <div class="form-step">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required value="<?php echo htmlspecialchars($data['username']); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($data['email']); ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="button" class="prev-btn">Previous <i class="fas fa-arrow-left"></i></button>
                <button type="button" class="next-btn">Next <i class="fas fa-arrow-right"></i></button>
            </div>

            <!-- Step 3: Contact Info -->
            <div class="form-step">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" required value="<?php echo htmlspecialchars($data['phone_number']); ?>">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" required><?php echo htmlspecialchars($data['address']); ?></textarea>
                </div>
                <button type="button" class="prev-btn">Previous <i class="fas fa-arrow-left"></i></button>
                <button type="submit" class="submit-btn">Register <i class="fas fa-user-plus"></i></button>
            </div>
        </form>
        <br>
        <p>Already have an account? <a href="customer_login.php" style="color: #ff6b6b;"><b>Login here</b></a></p>
    </div>

    <script>
        const formSteps = document.querySelectorAll('.form-step');
        const steps = document.querySelectorAll('.step');
        const nextBtns = document.querySelectorAll('.next-btn');
        const prevBtns = document.querySelectorAll('.prev-btn');
        let currentStep = 0;

        nextBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (currentStep < formSteps.length - 1) {
                    formSteps[currentStep].classList.remove('active');
                    steps[currentStep].classList.remove('active');
                    currentStep++;
                    formSteps[currentStep].classList.add('active');
                    steps[currentStep].classList.add('active');
                }
            });
        });

        prevBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (currentStep > 0) {
                    formSteps[currentStep].classList.remove('active');
                    steps[currentStep].classList.remove('active');
                    currentStep--;
                    formSteps[currentStep].classList.add('active');
                    steps[currentStep].classList.add('active');
                }
            });
        });
    </script>
</body>
</html>