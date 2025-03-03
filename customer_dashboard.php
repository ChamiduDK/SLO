<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$success = '';
$error = '';

// Fetch selected gig
$gig_id = isset($_GET['gig_id']) ? intval($_GET['gig_id']) : 0;
$selected_gig = null;
if ($gig_id > 0) {
    $stmt = $connection->prepare("
        SELECT g.*, c.name AS category_name, u.username 
        FROM gigs g 
        LEFT JOIN categories c ON g.category_id = c.id 
        LEFT JOIN users u ON g.user_id = u.id 
        WHERE g.id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $gig_id);
        $stmt->execute();
        $selected_gig = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $error = "Failed to prepare gig query: " . $connection->error;
    }
}

// Handle gig request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $gig_id = intval($_POST['gig_id']);
    $requirements = htmlspecialchars(trim($_POST['requirements']));

    if (!empty($requirements)) {
        $stmt = $connection->prepare("INSERT INTO gig_requests (gig_id, customer_id, requirements, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        if ($stmt) {
            $stmt->bind_param("iis", $gig_id, $customer_id, $requirements);
            if ($stmt->execute()) {
                $success = "Request sent successfully!";
            } else {
                $error = "Failed to send request: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare insert query: " . $connection->error;
        }
    } else {
        $error = "Please provide your requirements.";
    }
}

// Handle adding to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $gig_request_id = intval($_POST['gig_request_id']);
    $quantity = 1;

    $stmt = $connection->prepare("INSERT INTO cart (customer_id, gig_request_id, quantity, type) VALUES (?, ?, ?, 'gig') ON DUPLICATE KEY UPDATE quantity = quantity + ?");
    if ($stmt) {
        $stmt->bind_param("iiii", $customer_id, $gig_request_id, $quantity, $quantity);
        if ($stmt->execute()) {
            $success = "Gig added to cart successfully!";
        } else {
            $error = "Failed to add to cart: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Failed to prepare cart insert: " . $connection->error;
    }
}

// Fetch gig requests
$stmt = $connection->prepare("
    SELECT gr.*, g.title, u.username AS seller_name 
    FROM gig_requests gr 
    JOIN gigs g ON gr.gig_id = g.id 
    JOIN users u ON g.user_id = u.id 
    WHERE gr.customer_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $error = "Failed to fetch requests: " . $connection->error;
}

// Fetch cart count
$cart_items = $connection->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = $customer_id")->fetch_assoc()['count'];

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thambapanni Heritage - Customer Dashboard</title>
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

        p.intro-text {
            color: var(--dark-maroon);
            font-size: 1rem;
            opacity: 0.8;
        }

        /* Messages */
        .success, .error {
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .success {
            background: var(--green);
            color: var(--white);
            border: 1px solid var(--green);
        }

        .error {
            background: var(--light-saffron);
            color: var(--dark-maroon);
            border: 1px solid var(--saffron);
        }

        /* Sections */
        .content-sections {
            background: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 20px;
        }

        .section {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        h2 {
            color: var(--maroon);
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        /* Gig Details */
        .gig-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
            background: var(--white);
            border-radius: 10px;
            border: 2px solid var(--saffron);
        }

        .gig-image {
            flex: 0 0 320px;
            height: 213px; /* 800/1200 * 320px = ~213px for 3:2 ratio */
        }

        .gig-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--saffron);
        }

        .gig-info {
            flex: 1;
            min-width: 200px;
        }

        .gig-info h3 {
            color: var(--maroon);
            font-size: 1.5rem;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .gig-info p {
            font-size: 0.95rem;
            color: var(--dark-maroon);
            margin: 8px 0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-maroon);
            font-size: 1rem;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--saffron);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--white);
            transition: var(--transition);
            color: var(--dark-maroon);
            height: 120px;
            resize: vertical;
        }

        textarea:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
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
            width: 100%;
            max-width: 200px;
        }

        button:hover {
            background: var(--gold);
            color: var(--dark-maroon);
            transform: translateY(-2px);
        }

        /* Request List */
        .request-list {
            list-style: none;
            padding: 0;
        }

        .request-list li {
            padding: 15px;
            background: var(--light-saffron);
            border-radius: 10px;
            margin-bottom: 15px;
            transition: var(--transition);
        }

        .request-list li:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .request-list li p {
            font-size: 0.95rem;
            color: var(--dark-maroon);
            margin: 5px 0;
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
            h2 {
                font-size: 1.5rem;
            }
            .gig-details {
                flex-direction: column;
            }
            .gig-image {
                flex: 0 0 100%;
                height: 200px;
            }
            button {
                max-width: 100%;
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
            h2 {
                font-size: 1.3rem;
            }
            p.intro-text {
                font-size: 0.9rem;
            }
            textarea {
                font-size: 0.9rem;
                padding: 8px;
            }
            button {
                font-size: 0.9rem;
                padding: 8px 15px;
            }
            .gig-image {
                height: 160px;
            }
            .request-list li {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>Customer Dashboard</h3>
                <i class="fas fa-times close-sidebar" id="closeSidebar"></i>
            </div>
            <ul class="sidebar-menu">
                <li class="active" data-section="selected-gig"><i class="fas fa-briefcase"></i> Selected Gig</li>
                <li data-section="your-requests"><i class="fas fa-envelope"></i> Your Requests</li>
                <li><a href="gigs.php"><i class="fas fa-list"></i> Browse Gigs</a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
                <li><a href="cart.php"><i class="fas fa-cart-plus"></i> Cart (<?php echo $cart_items; ?>)</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Welcome to Your Dashboard!</h1>
                <p class="intro-text">Submit requirements for gigs and track your requests.</p>
            </div>

            <?php if (!empty($success)) { echo "<div class='success'>$success</div>"; } ?>
            <?php if (!empty($error)) { echo "<div class='error'>$error</div>"; } ?>

            <div class="content-sections">
                <!-- Selected Gig Section -->
                <section id="selected-gig" class="section active">
                    <h2>Selected Gig</h2>
                    <?php if ($selected_gig) { ?>
                        <div class="gig-details">
                            <div class="gig-image">
                                <?php if (!empty($selected_gig['image'])) { ?>
                                    <img src="<?php echo htmlspecialchars($selected_gig['image']); ?>" alt="<?php echo htmlspecialchars($selected_gig['title']); ?>">
                                <?php } else { ?>
                                    <div class="placeholder">No Image</div>
                                <?php } ?>
                            </div>
                            <div class="gig-info">
                                <h3><?php echo htmlspecialchars($selected_gig['title']); ?></h3>
                                <p><?php echo htmlspecialchars($selected_gig['description']); ?></p>
                                <p><strong>Price:</strong> LKR <?php echo number_format($selected_gig['price'], 2); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($selected_gig['category_name']); ?></p>
                                <p><strong>Provider:</strong> <?php echo htmlspecialchars($selected_gig['username']); ?></p>
                                <form method="POST">
                                    <input type="hidden" name="gig_id" value="<?php echo $selected_gig['id']; ?>">
                                    <div class="form-group">
                                        <label>Your Requirements</label>
                                        <textarea name="requirements" placeholder="Enter your requirements..." required></textarea>
                                    </div>
                                    <button type="submit" name="submit_request">Send Request <i class="fas fa-paper-plane"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php } else { ?>
                        <p>No gig selected. <a href="gigs.php">Browse gigs</a> to choose one.</p>
                    <?php } ?>
                </section>

                <!-- Your Requests Section -->
                <section id="your-requests" class="section">
                    <h2>Your Gig Requests</h2>
                    <?php if (empty($requests)) { ?>
                        <p>No requests submitted yet.</p>
                    <?php } else { ?>
                        <ul class="request-list">
                            <?php foreach ($requests as $request) { ?>
                                <li>
                                    <p><strong>Gig:</strong> <?php echo htmlspecialchars($request['title']); ?></p>
                                    <p><strong>Provider:</strong> <?php echo htmlspecialchars($request['seller_name']); ?></p>
                                    <p><strong>Requirements:</strong> <?php echo htmlspecialchars($request['requirements']); ?></p>
                                    <p><strong>Status:</strong> <?php echo htmlspecialchars($request['status']); ?></p>
                                    <?php if ($request['status'] === 'responded') { ?>
                                        <p><strong>Seller Response:</strong> <?php echo htmlspecialchars($request['seller_response']); ?></p>
                                        <p><strong>Price:</strong> LKR <?php echo number_format($request['price'], 2); ?></p>
                                        <form method="POST">
                                            <input type="hidden" name="gig_request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="add_to_cart">Add to Cart <i class="fas fa-cart-plus"></i></button>
                                        </form>
                                    <?php } ?>
                                    <p><small>Requested on: <?php echo $request['created_at']; ?></small></p>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.sidebar-menu li[data-section]');
            const sections = document.querySelectorAll('.section');
            const menuToggle = document.getElementById('menuToggle');
            const closeSidebar = document.getElementById('closeSidebar');
            const sidebar = document.querySelector('.sidebar');

            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const sectionId = this.getAttribute('data-section');
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    sections.forEach(section => {
                        section.classList.remove('active');
                        if (section.id === sectionId) {
                            section.classList.add('active');
                        }
                    });
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                    }
                });
            });

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