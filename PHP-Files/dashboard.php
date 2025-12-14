<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
} else {
    echo "<h1>Welcome to your Dashboard, {$_SESSION['user_name']} . !</h1>";
    echo "<p>This is a protected area only accessible to logged-in users.</p>";
    echo "<a href='logout.php'>Logout</a>";
}

?>