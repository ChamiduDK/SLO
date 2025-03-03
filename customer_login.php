<?php
session_start();
require_once 'db_connect.php';

function sanitize($data, $conn) {
    return htmlspecialchars(strip_tags(trim(mysqli_real_escape_string($conn, $data))));
}

$errors = [];
$login_field = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_field = sanitize($_POST['login_field'], $connection);
    $password = sanitize($_POST['password'], $connection);

    if (empty($login_field) || empty($password)) {
        $errors[] = "All fields are required.";
    } else {
        $stmt = $connection->prepare("SELECT id, username, password FROM customers WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_field, $login_field);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $customer = $result->fetch_assoc();
            if (password_verify($password, $customer['password'])) {
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_username'] = $customer['username'];
                header("Location: shop.php");
                exit;
            } else {
                $errors[] = "Invalid password.";
            }
        } else {
            $errors[] = "No account found.";
        }
        $stmt->close();
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
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
        <h2>Customer Login</h2>
        <?php if (!empty($errors)) { echo '<div class="error-message">' . implode('<br>', $errors) . '</div>'; } ?>
        <form action="customer_login.php" method="POST">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="login_field" required value="<?php echo htmlspecialchars($login_field); ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" style="background-color:#ff6b6b; color:#fff;">Login <i class="fas fa-sign-in-alt"></i></button>
        </form>
        <br>
        <p>New here? <a href="customer_registration.php" style="color:#ff6b6b;"><b>Register</b></a></p>
    </div>
</body>
</html>