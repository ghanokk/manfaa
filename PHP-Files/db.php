<?php
$server = "localhost";
$user = "root";
$pass = "";
$dbname = "ifada_db";

$conn = new mysqli($server, $user, $pass, $dbname);

/*
    === Test Connection ===
    
if (!$conn){
    echo "Connection failed: {$conn->connect_error}";
} else {
    echo "Connected successfully!";
}
    */
?>