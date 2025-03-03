<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit;
}

$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : ''; // Get search term
$categories = $connection->query("SELECT * FROM categories WHERE type = 'gig'")->fetch_all(MYSQLI_ASSOC);

$query = "SELECT g.*, c.name AS category_name, u.username, u.id AS user_id 
          FROM gigs g 
          LEFT JOIN categories c ON g.category_id = c.id 
          LEFT JOIN users u ON g.user_id = u.id WHERE 1=1"; // Base query with 1=1 for easy appending
if ($selected_category > 0) {
    $query .= " AND g.category_id = $selected_category";
}
if (!empty($search_query)) {
    $search_query = $connection->real_escape_string($search_query); // Prevent SQL injection
    $query .= " AND (g.title LIKE '%$search_query%' OR g.description LIKE '%$search_query%' OR c.name LIKE '%$search_query%')";
}
$gigs = $connection->query($query)->fetch_all(MYSQLI_ASSOC);

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thambapanni Heritage - Gigs</title>
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

        /* Gig Grid */
        .gig-grid {
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

        .gig {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .gig:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .gig-image {
            width: 100%;
            height: 250px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .gig-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid var(--saffron);
            transition: var(--transition);
        }

        .gig:hover .gig-image img {
            transform: scale(1.05);
        }

        .gig-image.placeholder {
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

        .gig-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .gig h3 {
            color: var(--maroon);
            font-size: 1.6rem;
            margin-bottom: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gig p {
            font-size: 1rem;
            color: var(--dark-maroon);
            margin: 6px 0;
        }

        .gig p.description {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0.85;
        }

        .gig p strong {
            color: var(--green);
        }

        .gig a {
            text-decoration: none;
        }

        .gig button {
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
            margin-top: 20px;
        }

        .gig button:hover {
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

            .gig-grid {
                grid-template-columns: 1fr;
            }

            .gig-image {
                height: 200px;
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

            .gig h3 {
                font-size: 1.4rem;
            }

            .gig p {
                font-size: 0.95rem;
            }

            .gig-image {
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
                <a href="shop.php">Shop</a>
                <a href="cart.php" class="cart-link">Cart <i class="fas fa-shopping-cart"></i> (<?php echo $cart_items; ?>)</a>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>

        <h1>Thambapanni Heritage Gigs</h1>
        <p class="intro-text">Explore services from Sri Lankan artists, showcasing our rich cultural diversity and traditional craftsmanship.</p>

        <div class="search-bar">
            <form action="gigs.php" method="GET">
                <input type="text" name="search" placeholder="Search gigs..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>

        <div class="categories">
            <a href="gigs.php" class="<?php echo $selected_category == 0 ? 'active' : ''; ?>">All</a>
            <?php foreach ($categories as $cat) { ?>
                <a href="gigs.php?category=<?php echo $cat['id']; ?>" class="<?php echo $selected_category == $cat['id'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php } ?>
        </div>

        <div class="gig-grid">
            <?php if (empty($gigs)) { ?>
                <p>No gigs found.</p>
            <?php } else { ?>
                <?php foreach ($gigs as $gig) { ?>
                    <div class="gig">
                        <div class="gig-image <?php echo empty($gig['image']) ? 'placeholder' : ''; ?>">
                            <?php if (!empty($gig['image'])) { ?>
                                <img src="<?php echo htmlspecialchars($gig['image']); ?>" alt="<?php echo htmlspecialchars($gig['title']); ?>">
                            <?php } else { ?>
                                No Image
                            <?php } ?>
                        </div>
                        <div class="gig-details">
                            <h3><?php echo htmlspecialchars($gig['title']); ?></h3>
                            <p class="description"><?php echo htmlspecialchars($gig['description']); ?></p>
                            <p><strong>Price:</strong> LKR <?php echo number_format($gig['price'], 2); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($gig['category_name']); ?></p>
                            <p><strong>Provider:</strong> <?php echo htmlspecialchars($gig['username']); ?></p>
                            <a href="customer_dashboard.php?gig_id=<?php echo $gig['id']; ?>">
                                <button>Choose Gig <i class="fas fa-arrow-right"></i></button>
                            </a>
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