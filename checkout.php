<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$cart_items = $connection->query("SELECT c.id, c.product_id, c.quantity, p.name, p.price 
                                  FROM cart c JOIN products p ON c.product_id = p.id 
                                  WHERE c.customer_id = $customer_id")->fetch_all(MYSQLI_ASSOC);

if (empty($cart_items)) {
    header("Location: cart.php");
    exit;
}

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

$customer = $connection->query("SELECT * FROM customers WHERE id = $customer_id")->fetch_assoc();

// Handle payment completion (PayHere callback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    if ($status === 'SUCCESS') {
        $stmt = $connection->prepare("INSERT INTO orders (customer_id, total) VALUES (?, ?)");
        $stmt->bind_param("id", $customer_id, $total);
        $stmt->execute();
        $new_order_id = $connection->insert_id;

        foreach ($cart_items as $item) {
            $stmt = $connection->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $new_order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }

        $connection->query("DELETE FROM cart WHERE customer_id = $customer_id");
        header("Location: shop.php?success=Order placed successfully!");
        exit;
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <style>
        .container { max-width: 600px; margin: 20px auto; padding: 20px; }
        .payhere-form { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Checkout</h1>
        <p><strong>Total:</strong> LKR <?php echo number_format($total, 2); ?></p>
        <p><strong>Shipping to:</strong> <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ', ' . $customer['address']); ?></p>

        <!-- PayHere Sandbox Form -->
        <div class="payhere-form">
            <form method="post" action="https://sandbox.payhere.lk/pay/checkout">
                <input type="hidden" name="merchant_id" value="YOUR_MERCHANT_ID">
                <input type="hidden" name="return_url" value="http://yourdomain.com/checkout.php">
                <input type="hidden" name="cancel_url" value="http://yourdomain.com/cart.php">
                <input type="hidden" name="notify_url" value="http://yourdomain.com/checkout.php">
                <input type="hidden" name="order_id" value="<?php echo uniqid(); ?>">
                <input type="hidden" name="items" value="Order from Thambapanni Heritage">
                <input type="hidden" name="currency" value="LKR">
                <input type="hidden" name="amount" value="<?php echo $total; ?>">
                <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>">
                <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($customer['phone_number']); ?>">
                <input type="hidden" name="address" value="<?php echo htmlspecialchars($customer['address']); ?>">
                <input type="hidden" name="city" value="Colombo">
                <input type="hidden" name="country" value="Sri Lanka">
                <input type="submit" value="Pay with PayHere">
            </form>
        </div>
    </div>
</body>
</html>