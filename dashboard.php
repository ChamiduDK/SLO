<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

function sanitize($data, $conn) {
    return htmlspecialchars(strip_tags(trim(mysqli_real_escape_string($conn, $data))));
}

$errors = [];
$success = '';

$gig_categories = $connection->query("SELECT * FROM categories WHERE type = 'gig'")->fetch_all(MYSQLI_ASSOC);
$product_categories = $connection->query("SELECT * FROM categories WHERE type = 'product'")->fetch_all(MYSQLI_ASSOC);

// Fetch user_id securely
$user_id_query = $connection->prepare("SELECT id FROM users WHERE username = ?");
$user_id_query->bind_param("s", $_SESSION['username']);
$user_id_query->execute();
$user_id_result = $user_id_query->get_result()->fetch_assoc();
$user_id = $user_id_result['id'] ?? null;
$user_id_query->close();

if (!$user_id) {
    $errors[] = "User not found.";
}

// Handle gig submission, product submission, and gig response (unchanged PHP logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gig'])) {
    $title = sanitize($_POST['title'], $connection);
    $description = sanitize($_POST['description'], $connection);
    $price = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $upload_dir = 'uploads/';
    $image = '';

    if (empty($title) || empty($description) || $price <= 0 || $category_id <= 0) {
        $errors[] = "All gig fields are required, price must be positive, and a category must be selected.";
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $image = $upload_dir . uniqid() . '.' . $file_ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                $errors[] = "Failed to upload gig image.";
            }
        }

        if (empty($errors)) {
            $stmt = $connection->prepare("INSERT INTO gigs (user_id, title, description, price, category_id, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdis", $user_id, $title, $description, $price, $category_id, $image);
            if ($stmt->execute()) {
                $success = "Gig added successfully!";
            } else {
                $errors[] = "Failed to add gig: " . $connection->error;
            }
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = sanitize($_POST['name'], $connection);
    $description = sanitize($_POST['description'], $connection);
    $price = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $upload_dir = 'uploads/';
    $image = '';

    if (empty($name) || empty($description) || $price <= 0 || $category_id <= 0) {
        $errors[] = "All product fields are required, price must be positive, and a category must be selected.";
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $image = $upload_dir . uniqid() . '.' . $file_ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                $errors[] = "Failed to upload product image.";
            }
        }

        if (empty($errors)) {
            $stmt = $connection->prepare("INSERT INTO products (user_id, name, description, price, image, category_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdsi", $user_id, $name, $description, $price, $image, $category_id);
            if ($stmt->execute()) {
                $success = "Product added successfully!";
            } else {
                $errors[] = "Failed to add product: " . $connection->error;
            }
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_request'])) {
    $request_id = intval($_POST['request_id']);
    $response = sanitize($_POST['seller_response'], $connection);
    $price = floatval($_POST['price']);

    if (!empty($response) && $price > 0) {
        $stmt = $connection->prepare("UPDATE gig_requests SET seller_response = ?, price = ?, status = 'responded' WHERE id = ? AND gig_id IN (SELECT id FROM gigs WHERE user_id = ?)");
        $stmt->bind_param("sdii", $response, $price, $request_id, $user_id);
        if ($stmt->execute()) {
            $success = "Response sent successfully!";
        } else {
            $errors[] = "Failed to send response: " . $connection->error;
        }
        $stmt->close();
    } else {
        $errors[] = "Response and price are required.";
    }
}

$gigs = $connection->query("SELECT g.*, c.name AS category_name FROM gigs g LEFT JOIN categories c ON g.category_id = c.id WHERE g.user_id = $user_id")->fetch_all(MYSQLI_ASSOC);
$products = $connection->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.user_id = $user_id")->fetch_all(MYSQLI_ASSOC);

$stmt = $connection->prepare("
    SELECT gr.*, g.title, u.username AS customer_name 
    FROM gig_requests gr 
    JOIN gigs g ON gr.gig_id = g.id 
    JOIN users u ON gr.customer_id = u.id 
    WHERE g.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thambapanni Heritage - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
/* Reset and Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    /* Sri Lankan Flag Colors with Adjustments */
    --saffron: #FF9933;         /* Vibrant orange for accents */
    --green: #00843D;           /* Deep green for primary actions */
    --maroon: #8C2A3C;          /* Rich maroon for backgrounds */
    --gold: #FFC107;            /* Bright gold for highlights */
    --white: #FFFFFF;           /* Clean white for main background */
    --gray: #F5F5F5;            /* Light gray for subtle contrast */
    --dark-maroon: #5C1A28;     /* Darker maroon for text */
    --light-saffron: #FFDAB3;   /* Lighter saffron for backgrounds */
    --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
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

p {
    color: var(--dark-maroon);
    font-size: 1rem;
    opacity: 0.8;
}

/* Messages */
.error-message, .success-message {
    padding: 12px;
    margin: 15px 0;
    border-radius: 8px;
    text-align: center;
    font-size: 0.95rem;
    font-weight: 500;
}

.error-message {
    background: var(--light-saffron);
    color: var(--dark-maroon);
    border: 1px solid var(--saffron);
}

.success-message {
    background: var(--green);
    color: var(--white);
    border: 1px solid var(--green);
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

input, textarea, select {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--saffron);
    border-radius: 8px;
    font-size: 1rem;
    background: var(--white);
    transition: var(--transition);
    color: var(--dark-maroon);
}

input:focus, textarea:focus, select:focus {
    border-color: var(--gold);
    outline: none;
    box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
}

textarea {
    height: 120px;
    resize: vertical;
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
}

button:hover {
    background: var(--gold);
    color: var(--dark-maroon);
    transform: translateY(-2px);
}

/* Item List */
.item-list {
    list-style: none;
    padding: 0;
}

.item-list li {
    padding: 15px;
    background: var(--light-saffron);
    border-radius: 10px;
    margin-bottom: 15px;
    transition: var(--transition);
}

.item-list li:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.item-list li img {
    max-width: 120px;
    height: auto;
    border-radius: 8px;
    margin-top: 10px;
    border: 2px solid var(--saffron);
}

/* Request Response */
.request-response {
    margin-top: 15px;
    padding: 15px;
    background: var(--white);
    border: 2px solid var(--saffron);
    border-radius: 10px;
    transition: var(--transition);
}

.request-response.sent {
    width: 500px;
    background: var(--green);
    color: var(--white);
    border: none;
    padding: 20px;
}

.request-response.sent p {
    margin: 5px 0;
    color: var(--white);
}

.request-response.sent .form-group,
.request-response.sent button {
    display: none;
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
    input, textarea, select {
        padding: 10px;
        font-size: 0.95rem;
    }
    button {
        padding: 10px 20px;
        font-size: 0.95rem;
    }
    .item-list li img {
        max-width: 100px;
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
    p {
        font-size: 0.9rem;
    }
    input, textarea, select {
        font-size: 0.9rem;
        padding: 8px;
    }
    button {
        font-size: 0.9rem;
        padding: 8px 15px;
    }
    .item-list li {
        padding: 10px;
    }
    .item-list li img {
        max-width: 80px;
    }
}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>Dashboard</h3>
                <i class="fas fa-times close-sidebar" id="closeSidebar"></i>
            </div>
            <ul class="sidebar-menu">
                <li class="active" data-section="add-gig"><i class="fas fa-plus-circle"></i> Add Gig</li>
                <li data-section="add-product"><i class="fas fa-shopping-bag"></i> Add Product</li>
                <li data-section="your-gigs"><i class="fas fa-briefcase"></i> Your Gigs</li>
                <li data-section="your-products"><i class="fas fa-box"></i> Your Products</li>
                <li data-section="gig-requests"><i class="fas fa-envelope"></i> Gig Requests</li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p>Manage your gigs and products showcasing Sri Lankan cultural craftsmanship.</p>
            </div>

            <?php if (!empty($errors)) { ?>
                <div class="error-message"><?php echo implode('<br>', $errors); ?></div>
            <?php } ?>
            <?php if (!empty($success)) { ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php } ?>

            <!-- Sections -->
            <div class="content-sections">
                <section id="add-gig" class="section active">
                    <h2>Add a Gig</h2>
                    <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_gig" value="1">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($gig_categories as $cat) { ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Price (LKR)</label>
                            <input type="number" name="price" required step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit">Add Gig <i class="fas fa-plus"></i></button>
                    </form>
                </section>

                <section id="add-product" class="section">
                    <h2>Add a Product</h2>
                    <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_product" value="1">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($product_categories as $cat) { ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Price (LKR)</label>
                            <input type="number" name="price" required step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit">Add Product <i class="fas fa-plus"></i></button>
                    </form>
                </section>

                <section id="your-gigs" class="section">
                    <h2>Your Gigs</h2>
                    <?php if (empty($gigs)) { ?>
                        <p>No gigs added yet.</p>
                    <?php } else { ?>
                        <ul class="item-list">
                            <?php foreach ($gigs as $gig) { ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($gig['title']); ?></strong> - LKR <?php echo number_format($gig['price'], 2); ?><br>
                                    Category: <?php echo htmlspecialchars($gig['category_name']); ?><br>
                                    <?php echo htmlspecialchars($gig['description']); ?><br>
                                    <?php if (!empty($gig['image'])) { ?>
                                        <img src="<?php echo htmlspecialchars($gig['image']); ?>" alt="Gig Image">
                                    <?php } ?>
                                    <br><small>Posted on: <?php echo $gig['created_at']; ?></small>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </section>

                <section id="your-products" class="section">
                    <h2>Your Products</h2>
                    <?php if (empty($products)) { ?>
                        <p>No products added yet.</p>
                    <?php } else { ?>
                        <ul class="item-list">
                            <?php foreach ($products as $product) { ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong> - LKR <?php echo number_format($product['price'], 2); ?><br>
                                    Category: <?php echo htmlspecialchars($product['category_name']); ?><br>
                                    <?php echo htmlspecialchars($product['description']); ?><br>
                                    <?php if (!empty($product['image'])) { ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Product Image">
                                    <?php } ?>
                                    <br><small>Posted on: <?php echo $product['created_at']; ?></small>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </section>

                <section id="gig-requests" class="section">
                    <h2>Gig Requests</h2>
                    <?php if (empty($requests)) { ?>
                        <p>No gig requests yet.</p>
                    <?php } else { ?>
                        <ul class="item-list">
                            <?php foreach ($requests as $request) { ?>
                                <li>
                                    <strong>Gig:</strong> <?php echo htmlspecialchars($request['title']); ?><br>
                                    <strong>Customer ID:</strong> <?php echo isset($request['customer_id']) && !empty($request['customer_id']) ? htmlspecialchars($request['customer_id']) : 'Unknown Customer'; ?><br>
                                    <strong>Requirements:</strong> <?php echo htmlspecialchars($request['requirements']); ?><br>
                                    <strong>Status:</strong> <?php echo htmlspecialchars($request['status']); ?><br>
                                    <div class="request-response <?php echo $request['status'] === 'responded' ? 'sent' : ''; ?>">
                                        <?php if ($request['status'] === 'responded') { ?>
                                            <p><strong>Response:</strong> <?php echo htmlspecialchars($request['seller_response']); ?></p>
                                            <p><strong>Price:</strong> LKR <?php echo number_format($request['price'], 2); ?></p>
                                        <?php } else { ?>
                                            <form method="POST">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <div class="form-group">
                                                    <label>Response</label>
                                                    <textarea name="seller_response" required></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label>Price (LKR)</label>
                                                    <input type="number" name="price" required step="0.01" min="0">
                                                </div>
                                                <button type="submit" name="respond_request">Send Response <i class="fas fa-paper-plane"></i></button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                    <small>Requested on: <?php echo $request['created_at']; ?></small>
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

            // Sidebar navigation
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const sectionId = this.getAttribute('data-section');
                    
                    // Update active menu item
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    // Show selected section
                    sections.forEach(section => {
                        section.classList.remove('active');
                        if (section.id === sectionId) {
                            section.classList.add('active');
                        }
                    });

                    // Close sidebar on mobile after selection
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                    }
                });
            });

            // Toggle sidebar on mobile
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