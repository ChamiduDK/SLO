<?php
require_once 'db_connect.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
echo "Welcome, " . htmlspecialchars($_SESSION['username']) . "!";
echo "<br><a href='logout.php'>Logout</a>";
?>