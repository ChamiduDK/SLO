<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Start session for user authentication

require_once 'db_connect.php';

// Function to sanitize input
function sanitize($data, $conn) {
    return htmlspecialchars(strip_tags(trim(mysqli_real_escape_string($conn, $data))));
}

// Initialize variables
$errors = [];
$login_field = ''; // For username or email
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_field = isset($_POST['login_field']) ? sanitize($_POST['login_field'], $connection) : '';
    $password = isset($_POST['password']) ? sanitize($_POST['password'], $connection) : '';

    // Validation
    if (empty($login_field)) {
        $errors[] = "Username or email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // If no validation errors, check database
    if (empty($errors)) {
        $stmt = $connection->prepare(
            "SELECT username, email, password FROM users WHERE username = ? OR email = ?"
        );
        if ($stmt === false) {
            $errors[] = "Database error: " . $connection->error;
        } else {
            $stmt->bind_param("ss", $login_field, $login_field);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Successful login
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    header("Location: dashboard.php"); // Redirect to a dashboard or home page
                    exit;
                } else {
                    $errors[] = "Invalid password.";
                }
            } else {
                $errors[] = "No account found with that username or email.";
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
    <title>Thambapanni Heritage - Login</title>
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
            <h2>Login to Your Account</h2>
            <p>Welcome back! Please enter your details.</p>

            <?php if (!empty($errors)) { ?>
                <div class="error-message">
                    <?php echo implode('<br>', $errors); ?>
                </div>
            <?php } ?>

            <form id="loginForm" action="login.php" method="POST">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="login_field" required placeholder="Enter your username or email" value="<?php echo htmlspecialchars($login_field); ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="submit-btn">Login <i class="fas fa-sign-in-alt"></i></button>
            </form>

            <br>

            <div class="register-link">
                <p>Don't have an account? <a href="Registration.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>