<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Fetch cart items (updated to include gig image)
$cart_items = $connection->query("
    SELECT c.*, 
           CASE 
               WHEN c.type = 'product' THEN p.name 
               WHEN c.type = 'gig' THEN g.title 
           END AS item_name,
           CASE 
               WHEN c.type = 'product' THEN p.price 
               WHEN c.type = 'gig' THEN gr.price 
           END AS price,
           c.type,
           p.image AS product_image,
           g.image AS gig_image
    FROM cart c
    LEFT JOIN products p ON c.product_id = p.id AND c.type = 'product'
    LEFT JOIN gig_requests gr ON c.gig_request_id = gr.id AND c.type = 'gig'
    LEFT JOIN gigs g ON gr.gig_id = g.id
    WHERE c.customer_id = $customer_id
")->fetch_all(MYSQLI_ASSOC);

// Handle cart update or checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $cart_id => $quantity) {
        $quantity = intval($quantity);
        if ($quantity > 0) {
            $stmt = $connection->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("iii", $quantity, $cart_id, $customer_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $connection->prepare("DELETE FROM cart WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $cart_id, $customer_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: cart.php?success=Cart updated!");
}

// Placeholder for payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $success = "Payment processing placeholder - implement this!";
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thambapanni Heritage - Cart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Sri Lankan Flag Colors with Adjustments */
        :root {
            --saffron: #FF9933;
            --green: #00843D;
            --maroon: #8C2A3C;
            --gold: #FFC107;
            --white: #FFFFFF;
            --gray: #F5F5F5;
            --dark-maroon: #5C1A28;
            --light-saffron: #FFDAB3;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--gray) 0%, var(--white) 100%);
            color: var(--dark-maroon);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Wrapper */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: var(--maroon);
            color: var(--white);
            position: fixed;
            top: 0;
            left: -250px;
            height: 100%;
            transition: var(--transition);
            z-index: 1000;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 20px;
            background: var(--saffron);
            color: var(--dark-maroon);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close-sidebar {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .sidebar-menu {
            list-style: none;
            padding: 10px 0;
        }

        .sidebar-menu li {
            padding: 15px 20px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu li:hover,
        .sidebar-menu li.active {
            background: var(--gold);
            color: var(--dark-maroon);
        }

        .sidebar-menu li a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 0;
            transition: var(--transition);
        }

        .main-content.shifted {
            margin-left: 250px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }

        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            color: var(--maroon);
            cursor: pointer;
            position: absolute;
            top: 20px;
            left: 20px;
        }

        h1 {
            color: var(--maroon);
            font-size: 2rem;
            margin: 10px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Messages */
        .success {
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 500;
            background: var(--green);
            color: var(--white);
            border: 1px solid var(--green);
        }

        /* Cart Table */
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .cart-table th, .cart-table td {
            padding: 15px;
            border-bottom: 1px solid var(--saffron);
            text-align: left;
        }

        .cart-table th {
            background: var(--light-saffron);
            color: var(--dark-maroon);
            font-weight: 700;
        }

        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .cart-table img {
            width: 100px;
            height: 67px; /* 3:2 ratio for 1200x800 scaled down */
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--saffron);
        }

        .cart-table input[type="number"] {
            width: 60px;
            padding: 8px;
            border: 2px solid var(--saffron);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .cart-table input[type="number"]:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        }

        /* Buttons */
        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        button {
            background: var(--green);
            color: var(--white);
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button:hover {
            background: var(--gold);
            color: var(--dark-maroon);
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (min-width: 769px) {
            .sidebar {
                left: 0;
            }
            .main-content {
                margin-left: 250px;
            }
            .menu-toggle {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
                left: -200px;
            }
            .sidebar.active {
                left: 0;
            }
            .close-sidebar {
                display: block;
            }
            .menu-toggle {
                display: block;
            }
            .main-content {
                padding: 15px;
            }
            h1 {
                font-size: 1.8rem;
            }
            .cart-table {
                display: block;
                overflow-x: auto;
            }
            .cart-table th, .cart-table td {
                padding: 10px;
            }
            .cart-table img {
                width: 80px;
                height: 53px;
            }
            .button-group {
                flex-direction: column;
                align-items: stretch;
            }
            button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 180px;
                left: -180px;
            }
            h1 {
                font-size: 1.5rem;
            }
            .cart-table th, .cart-table td {
                font-size: 0.9rem;
                padding: 8px;
            }
            .cart-table img {
                width: 60px;
                height: 40px;
            }
            .cart-table input[type="number"] {
                width: 50px;
                padding: 6px;
                font-size: 0.9rem;
            }
            button {
                font-size: 0.9rem;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>Your Cart</h3>
                <i class="fas fa-times close-sidebar" id="closeSidebar"></i>
            </div>
            <ul class="sidebar-menu">
                <li><a href="gigs.php"><i class="fas fa-briefcase"></i> Gigs</a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
                <li><a href="customer_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Your Cart</h1>
            </div>

            <?php if (isset($_GET['success']) || isset($success)) { echo "<div class='success'>" . (isset($success) ? $success : htmlspecialchars($_GET['success'])) . "</div>"; } ?>

            <div class="content-sections">
                <?php if (empty($cart_items)) { ?>
                    <p>Your cart is empty.</p>
                <?php } else { ?>
                    <form method="POST">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Price (LKR)</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th>Image</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $grand_total = 0; ?>
                                <?php foreach ($cart_items as $item) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['type']); ?></td>
                                        <td><?php echo number_format($item['price'], 2); ?></td>
                                        <td><input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0"></td>
                                        <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        <td>
                                            <?php if ($item['type'] === 'product' && !empty($item['product_image'])) { ?>
                                                <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="Product Image">
                                            <?php } elseif ($item['type'] === 'gig' && !empty($item['gig_image'])) { ?>
                                                <img src="<?php echo htmlspecialchars($item['gig_image']); ?>" alt="Gig Image">
                                            <?php } else { ?>
                                                <div class="placeholder">No Image</div>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php $grand_total += $item['price'] * $item['quantity']; ?>
                                <?php } ?>
                                <tr>
                                    <td colspan="4" style="text-align: right;"><strong>Grand Total:</strong></td>
                                    <td><strong>LKR <?php echo number_format($grand_total, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="button-group">
                            <button type="submit" name="update_cart">Update Cart <i class="fas fa-sync-alt"></i></button>
                            <button type="submit" name="checkout">Proceed to Checkout <i class="fas fa-credit-card"></i></button>
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const closeSidebar = document.getElementById('closeSidebar');
            const sidebar = document.querySelector('.sidebar');

            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('active');
            });
        });
    </script>
</body>
</html>