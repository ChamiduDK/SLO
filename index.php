<?php
session_start();
require_once 'db_connect.php';

// Fetch the 3 latest products
$product_query = "SELECT p.*, c.name AS category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.id DESC LIMIT 3"; // Adjust 'id' to 'created_at' if you have a timestamp
$products = $connection->query($product_query)->fetch_all(MYSQLI_ASSOC);

// Fetch product categories
$category_query = "SELECT * FROM categories WHERE type = 'product'";
$categories = $connection->query($category_query)->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sri Lankan Artisans - Cultural Showcase</title>
    <link href="images/TH_logo_br.png" rel="icon">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
     .search-bar {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            animation: fadeInUp 1s ease 0.6s;
            animation-fill-mode: backwards;
        }

        .search-bar input {
            width: 60%;
            max-width: 400px;
            padding: 12px 20px;
            border: none;
            border-radius: 25px 0 0 25px;
            font-size: 16px;
            outline: none;
            background: #FFF;
            color: #333;
        }

        .search-bar button {
            padding: 12px 25px;
            border: none;
            border-radius: 0 25px 25px 0;
            background: #00A859; 
            color: #FFF;
            font-weight: 400;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-bar button:hover {
            background: #008040; 
        }

        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        
        .search-bar form {
            display: flex;
            width: 100%;
            justify-content: center;
        }
        
        /* Responsive Design */
@media (max-width: 768px) {
  .navbar {
      padding: 20px;
  }

  .nav-links {
      display: none;
      flex-direction: column;
      position: absolute;
      top: 70px;
      left: 0;
      width: 100%;
      background: #8C2F39; /* Maroon */
      padding: 20px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  }

  .nav-links.active {
      display: flex;
  }

  .hamburger {
      display: block;
  }

  .hero-content h1 {
      font-size: 32px;
  }

  .hero-content p {
      font-size: 16px;
  }

  .search-bar {
    flex-direction: column;
    gap: 15px; /* Space between input and button */
    width: 90%; /* Wider on mobile */
    margin: 20px;

}

.search-bar input {
    width: 100%;
    border-radius: 25px; /* Fully rounded when stacked */
    padding: 16px 20px; /* Larger padding for touch */
    font-size: 16px;
}

.search-bar button {
    width: 100%;
    max-width: 100px; /* Limit button width */
    border-radius: 25px; /* Fully rounded when stacked */
    font-size: 16px;
    margin-left: 10px;
    
}

  .category-grid {
      grid-template-columns: repeat(2, 1fr);
  }

  .product-grid {
      grid-template-columns: 1fr;
  }

  .contact-content {
      flex-direction: column;
      text-align: center;
  }

  .contact-info {
      text-align: center;
  }
}

@media (max-width: 480px) {
  .category-grid {
      grid-template-columns: 1fr;
  }

  .search-bar {
      flex-direction: column;
      gap: 10px;
      align-items: center;
  }

  .search-bar input {
      width: 100%;
      border-radius: 25px;
  }

  .search-bar button {
      border-radius: 25px;
  }
}
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="hero">
        <nav class="navbar">
            <div class="logo">Thambapanni Heritage</div>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#categories">Categories</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="shop.php">Shop</a></li>
                <li><a href="#">Learn</a></li>
                <li><a href="#" style="color: #FFC107;"><b>Become a Seller</b></a></li>
                <li><a href="customer_login.php"><b>Sign in</b></a></li>
                <li><a href="customer_registration.php" class="btn2">Register</a></li>
            </ul>
            <div class="hamburger">☰</div>
        </nav>
    <div class="hero-content">
        <h1>Discover Sri Lanka's Cultural Treasures</h1>
        <p>Connecting artisans to the world through a modern platform.</p>
        <?php if (isset($_GET['message'])) { ?>
            <p><?php echo htmlspecialchars($_GET['message']); ?></p>
        <?php } ?>
        <div class="search-bar">
            <form action="search.php" method="GET">
                <input type="text" name="search" placeholder="Search for products or artisans..." id="search-input">
                <button type="submit" id="search-btn">Search</button>
            </form>
        </div>
        <a href="#about" class="btn">Learn More</a>
    </div>
    </header>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <h2>About Our Mission</h2>
            <p>Sri Lanka is globally recognized for its rich cultural diversity and traditional craftsmanship, including handicrafts, paintings, textiles, and sculptures. However, many talented artisans lack access to modern platforms to display their products and reach wider audiences. This project addresses this gap by offering a dedicated e-commerce and showcase platform tailored specifically for Sri Lankan cultural products.</p>
            <a href="Registration.php" class="btn secondary">Join Us</a>
        </div>
    </section>

    <!-- Categories Section -->
    <section id="categories" class="categories">
        <div class="container">
            <h2>Explore Categories</h2>
            <div class="category-grid">
                <div class="category-item">
                    <img src="images/handicrafts.jpg" alt="Handicrafts">
                    <h3>Handicrafts</h3>
                </div>
                <div class="category-item">
                    <img src="images/paintings.jpg" alt="Paintings">
                    <h3>Paintings</h3>
                </div>
                <div class="category-item">
                    <img src="images/textiles.jpg" alt="Textiles">
                    <h3>Textiles</h3>
                </div>
                <div class="category-item">
                    <img src="images/sculptures.jpg" alt="Sculptures">
                    <h3>Sculptures</h3>
                </div>
            </div>
        </div>
    </section>

<!-- Latest Products Section -->
    <section id="products" class="products">
        <div class="container">
            <h2>Latest Products</h2>
            <div class="product-grid">
                <?php if (empty($products)) { ?>
                    <p>No products available.</p>
                <?php } else { ?>
                    <?php foreach ($products as $product) { ?>
                        <div class="product-item">
                            <?php if (!empty($product['image'])) { ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php } else { ?>
                                <img src="images/placeholder.jpg" alt="No Image">
                            <?php } ?>
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p>LKR <?php echo number_format($product['price'], 2); ?></p>
                            <a href="shop.php?product_id=<?php echo $product['id']; ?>" class="btn">View Details</a>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
            <div class="view-all">
                <a href="shop.php" class="btn secondary">View All Products</a>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2>Contact Us</h2>
            <div class="contact-content">
                <div class="contact-info">
                    <p>Email: info@srilankanartisans.com</p>
                    <p>Phone: +94 11 234 5678</p>
                    <p>Address: 123 Cultural Lane, Colombo, Sri Lanka</p>
                </div>
                <form class="contact-form">
                    <input type="text" placeholder="Your Name" required>
                    <input type="email" placeholder="Your Email" required>
                    <textarea placeholder="Your Message" rows="5" required></textarea>
                    <button type="submit" class="btn">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>© 2025 Sri Lankan Artisans. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>



