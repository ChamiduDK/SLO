<?php
session_start();
require_once 'db_connect.php';

// Get filters
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$categories = $connection->query("SELECT * FROM categories WHERE type = 'product'")->fetch_all(MYSQLI_ASSOC);

// Build the product query
$query = "SELECT p.*, c.name AS category_name, u.username, u.id AS user_id 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN users u ON p.user_id = u.id 
          WHERE 1=1";

$params = array();
$types = '';

if ($product_id > 0) {
    $query .= " AND p.id = ?";
    $params[] = $product_id;
    $types .= 'i';
} elseif ($selected_category > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $selected_category;
    $types .= 'i';
}

if (!empty($search_query) && $product_id == 0) {
    $search_query = $connection->real_escape_string($search_query);
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= 'sss';
}

// Prepare and execute the query
$stmt = $connection->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['customer_id'])) {
        header("Location: customer_login.php?redirect=shop.php");
        exit;
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $customer_id = $_SESSION['customer_id'];

    $stmt = $connection->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
    $stmt->bind_param("iiii", $customer_id, $product_id, $quantity, $quantity);
    if ($stmt->execute()) {
        header("Location: shop.php?success=Added to cart!");
    } else {
        $error = "Failed to add to cart: " . $connection->error;
    }
    $stmt->close();
}

// Get cart items count
$cart_items = 0;
if (isset($_SESSION['customer_id'])) {
    $cart_items = $connection->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = " . $_SESSION['customer_id'])->fetch_assoc()['count'];
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thambapanni Heritage - Shop</title>
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

        /* Header Navigation */
        .nav {
            background: linear-gradient(to right, var(--maroon), var(--dark-maroon));
            padding: 25px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .nav-toggle {
            display: none;
            font-size: 1.5rem;
            color: var(--white);
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
        }

        .nav a {
            color: var(--white);
            text-decoration: none;
            font-size: 1rem;
            padding: 10px 20px;
            transition: var(--transition);
            border-radius: 25px;
        }

        .nav a:hover {
            background: var(--gold);
            color: var(--dark-maroon);
        }

        /* Title and Intro */
        h1 {
            color: var(--maroon);
            text-align: center;
            font-size: 2.5rem;
            margin: 30px 0 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
        }

        .intro-text {
            text-align: center;
            color: var(--dark-maroon);
            font-size: 1.1rem;
            margin-bottom: 30px;
            font-style: italic;
        }

        /* Messages */
        .success-message, .error-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .success-message {
            background: var(--green);
            color: var(--white);
            border: 1px solid var(--green);
        }

        .error-message {
            background: var(--light-saffron);
            color: var(--dark-maroon);
            border: 1px solid var(--saffron);
        }

        /* Search Bar */
        .search-bar {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .search-bar form {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 500px;
        }

        .search-bar input[type="text"] {
            flex-grow: 1;
            padding: 12px;
            border: 2px solid var(--saffron);
            border-radius: 25px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .search-bar input[type="text"]:focus {
            border-color: var(--gold);
            box-shadow: 0 0 8px rgba(255, 193, 7, 0.5);
            outline: none;
        }

        .search-bar button {
            background: var(--green);
            color: var(--white);
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .search-bar button:hover {
            background: var(--gold);
            color: var(--dark-maroon);
        }

        /* Categories */
        .categories {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
        }

        .categories a {
            background: var(--white);
            color: var(--maroon);
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 1rem;
            border: 2px solid var(--saffron);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .categories a.active, .categories a:hover {
            background: var(--saffron);
            color: var(--white);
            border-color: var(--gold);
            transform: scale(1.05);
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            padding: 20px;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .product {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .product:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            width: 100%;
            height: 250px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid var(--saffron);
            transition: var(--transition);
        }

        .product:hover .product-image img {
            transform: scale(1.05);
        }

        .product-image.placeholder {
            background: var(--light-saffron);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-maroon);
            font-size: 1.2rem;
            font-weight: 500;
            border-radius: 15px;
            border: 3px solid var(--saffron);
        }

        .product-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product h3 {
            color: var(--maroon);
            font-size: 1.6rem;
            margin-bottom: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product p {
            font-size: 1rem;
            color: var(--dark-maroon);
            margin: 6px 0;
        }

        .product p.description {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0.85;
        }

        .product p strong {
            color: var(--green);
        }

        .product form {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .product input[type="number"] {
            width: 70px;
            padding: 10px;
            border: 2px solid var(--saffron);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .product input[type="number"]:focus {
            border-color: var(--gold);
            box-shadow: 0 0 8px rgba(255, 193, 7, 0.5);
        }

        button {
            background: var(--green);
            color: var(--white);
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow);
        }

        button:hover {
            background: var(--gold);
            color: var(--dark-maroon);
            transform: scale(1.05);
        }


        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .nav {
                justify-content: space-between;
                padding: 10px 15px;
            }

            .nav-toggle {
                display: block;
            }

            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                background: var(--maroon);
                position: absolute;
                top: 100%;
                left: 0;
                padding: 15px 0;
                box-shadow: var(--shadow);
            }

            .nav-links.active {
                display: flex;
            }

            .nav a {
                width: 100%;
                text-align: center;
                padding: 15px;
                font-size: 1.1rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .nav a:last-child {
                border-bottom: none;
            }

            .nav a:hover {
                background: var(--saffron);
            }

            h1 {
                font-size: 2rem;
            }

            .intro-text {
                font-size: 1rem;
            }

            .search-bar form {
                flex-direction: column;
                padding: 0 15px;
            }

            .search-bar input[type="text"] {
                width: 100%;
            }

            .search-bar button {
                width: 100%;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-image {
                height: 200px;
            }

            .product form {
                flex-direction: column;
                align-items: stretch;
            }

            .product input[type="number"] {
                width: 100%;
            }

            button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.6rem;
            }

            .categories a {
                padding: 10px 20px;
                font-size: 0.95rem;
            }

            .product h3 {
                font-size: 1.4rem;
            }

            .product p {
                font-size: 0.95rem;
            }

            .product-image {
                height: 150px;
            }

            .nav a {
                font-size: 1rem;
                padding: 12px;
            }

            .nav-toggle {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <button class="nav-toggle"><i class="fas fa-bars"></i></button>
            <div class="nav-links">
                <a href="gigs.php">Gigs</a>
                <a href="cart.php" class="cart-link">Cart <i class="fas fa-shopping-cart"></i> (<?php echo $cart_items; ?>)</a>
                <a href="customer_dashboard.php">Dashboard</a>
                <?php if (isset($_SESSION['customer_id'])) { ?>
                    <a href="logout.php">Logout</a>
                <?php } else { ?>
                    <a href="customer_login.php">Login</a>
                <?php } ?>
            </div>
        </div>

        <h1>Thambapanni Heritage Shop</h1>
        <p class="intro-text">Discover Sri Lanka's rich cultural diversity and traditional craftsmanship.</p>
        
        <?php if (isset($_GET['success']) && isset($_SESSION['customer_id'])) { ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php } ?>
        <?php if (isset($error)) { ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <div class="search-bar">
            <form action="shop.php" method="GET">
                <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>

        <div class="categories">
            <a href="shop.php" class="<?php echo $selected_category == 0 ? 'active' : ''; ?>">All</a>
            <?php foreach ($categories as $cat) { ?>
                <a href="shop.php?category=<?php echo $cat['id']; ?>" class="<?php echo $selected_category == $cat['id'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php } ?>
        </div>

        <div class="product-grid">
            <?php if (empty($products)) { ?>
                <p>No products found.</p>
            <?php } else { ?>
                <?php foreach ($products as $product) { ?>
                    <div class="product">
                        <div class="product-image <?php echo empty($product['image']) ? 'placeholder' : ''; ?>">
                            <?php if (!empty($product['image'])) { ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php } else { ?>
                                No Image
                            <?php } ?>
                        </div>
                        <div class="product-details">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <p><strong>Price:</strong> LKR <?php echo number_format($product['price'], 2); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></p>
                            <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['username']); ?></p>
                            <form action="shop.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="number" name="quantity" value="1" min="1">
                                <button type="submit" name="add_to_cart">Add to Cart <i class="fas fa-cart-plus"></i></button>
                            </form>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>

    <script>
        document.querySelector('.nav-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
    </script>
</body>
</html>