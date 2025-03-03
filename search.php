<?php
session_start();
require_once 'db_connect.php';

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    
    if (!empty($search_query)) {
        $search_query = $connection->real_escape_string($search_query);

        // Search products
        $product_query = "SELECT COUNT(*) as product_count 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.name LIKE '%$search_query%' 
                         OR p.description LIKE '%$search_query%' 
                         OR c.name LIKE '%$search_query%'";
        $product_result = $connection->query($product_query)->fetch_assoc();
        $product_count = $product_result['product_count'];

        // Search gigs
        $gig_query = "SELECT COUNT(*) as gig_count 
                      FROM gigs g 
                      LEFT JOIN categories c ON g.category_id = c.id 
                      WHERE g.title LIKE '%$search_query%' 
                      OR g.description LIKE '%$search_query%' 
                      OR c.name LIKE '%$search_query%'";
        $gig_result = $connection->query($gig_query)->fetch_assoc();
        $gig_count = $gig_result['gig_count'];

        // Redirect logic
        if ($product_count > 0 && $gig_count == 0) {
            header("Location: shop.php?search=" . urlencode($search_query));
            exit;
        } elseif ($gig_count > 0 && $product_count == 0) {
            header("Location: gigs.php?search=" . urlencode($search_query));
            exit;
        } elseif ($product_count > 0 && $gig_count > 0) {
            // If both have results, prioritize shop (or adjust logic as needed)
            header("Location: shop.php?search=" . urlencode($search_query));
            exit;
        } else {
            // No results, redirect back to index with a message
            header("Location: index.php?message=" . urlencode("No products or gigs found for '$search_query'."));
            exit;
        }
    } else {
        // Empty search, redirect back to index
        header("Location: index.php");
        exit;
    }
} else {
    // No search submitted, redirect back to index
    header("Location: index.php");
    exit;
}

$connection->close();
?>