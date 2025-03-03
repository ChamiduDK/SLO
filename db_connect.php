<?php

$host = '127.0.0.1:3306';             
$username = 'u866533411_chamidudhilsha';  
$password = 'qazwsx123ED@#';          
$dbname = 'u866533411_chamidudhilsha';    

$connection = new mysqli($host, $username, $password, $dbname);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}
?>