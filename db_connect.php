<?php
// Database credentials
$host = '127.0.0.1:3306';             // Database host
$username = 'u866533411_chamidudhilsha';  // Database username
$password = 'qazwsx123ED@#';          // Database password
$dbname = 'u866533411_chamidudhilsha';    // Database name

// Create connection
$connection = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}
// Optional: Uncomment to debug connection
// echo "Connected successfully!";
?>