<?php
session_start();
include 'db.php';
$sql = "SELECT * FROM courses";
// $result = $conn->query($sql);
$result =  mysqli_query($conn, $sql);
if (!$result) {
    die("Error retrieving courses: " . mysqli_error($conn));
}else {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Courses</title>
    <link rel="stylesheet" href="../styles/Courses.css">
</head>
<body>
    <header class="site-header">
        <h1>Available Courses</h1>
        <nav class="navbar">
           <a class="link" href="home.php">Ifada</a>

            <form class="nav-search" role="search">
                <input type="search" placeholder="Search courses..." aria-label="Search courses">
            </form>

            <div class="nav-right">
                <a class="link" href="login.php">Sign in</a>
            </div>
        </nav>
    </header>

    <main class="container">

        <section class="controls">
            <select aria-label="Filter by category">
                <option value="">All Levels</option>
                <option>Beginner</option>
                <option>Intermediate</option>
                <option>Advanced</option>
            </select>
        </section>

   <section class="courses-grid">
    
    <!--
    courses-grid
        ├─ card (course 1)
        ├─ card (course 2)
        ├─ card (course 3)
     -->
        <?php
        mysqli_data_seek($result, 0); // It moves the MySQL result pointer back to the first row
        while($row = mysqli_fetch_assoc($result)){
        ?>
            <article class="card">
                <div class="thumb">
                    <img src="../<?php echo htmlspecialchars($row['image']); ?>" alt="Course thumbnail">
                </div>
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p class="meta"><?php echo htmlspecialchars($row['level']); ?></p>
                <p class="desc"><?php echo htmlspecialchars($row['description']); ?></p>
                <button>View course</button>
            </article>
        <?php } ?>
    </section>

</main>

    <footer class="site-footer">
        <small>© Ifada — All rights reserved</small>
    </footer> 
</body>
</html>