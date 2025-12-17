<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Get cart count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Fetch popular courses (limit to 3)
$popularSql = "SELECT id, title, image FROM courses LIMIT 3";
$popularResult = $conn->query($popularSql);
$popularCourses = [];
if ($popularResult) {
    while ($row = $popularResult->fetch_assoc()) {
        $popularCourses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HomePage</title>
  <link rel="stylesheet" href="../styles/HomePage.css">
</head>
<body>
  <header class="site-header">
    <nav class="nav">
      <div class="brand">Ifada</div>
      <ul class="nav-links">
        <li><a href="hpage.php">Home</a></li>
        <li><a href="courses.php">Courses</a></li>
        <li><a href="event.php">Events</a></li>
        <li><a href="FAQ.html">FAQ</a></li>
        <?php if ($isLoggedIn): ?>
          <li><a href="dashboard.php" class="btn small">Dashboard</a></li>
          
          <li><a href="cart.php" class="btn small" style="margin-left: 30px;position: relative; background-color: #0864c5ff;color: white;">
            🛒 Cart
            <?php if ($cartCount > 0): ?>
                <span style="position: absolute; top: -8px; right: -10px; background-color: #dc3545; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;"><?php echo $cartCount; ?></span>
            <?php endif; ?>
          </a></li>
        <?php else: ?>
          <li><a href="login.php" class="btn small">Sign in</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <main>
    <section class="hero">
      <div class="hero-inner">
        <h1>Learn. Build. Grow.</h1>
        <p>Practical courses and hands‑on projects to level up your web development skills.</p>
        <div class="hero-ctas">
          <a href="courses.php" class="btn primary">Browse Courses</a>
          <a href="FAQ.html" class="btn ghost">How it works</a>
        </div>
      </div>
    </section>

    <section class="features container">
      <article class="feature">
        <div class="icon">📚</div>
        <h3>Curated curriculum</h3>
        <p>Short, focused courses designed for real projects.</p>
      </article>

      <article class="feature">
        <div class="icon">🧑‍🏫</div>
        <h3>Expert instructors</h3>
        <p>Learn from industry practitioners with real experience.</p>
      </article>

      <article class="feature">
        <div class="icon">🚀</div>
        <h3>Hands-on projects</h3>
        <p>Build a portfolio while you learn.</p>
      </article>
    </section>

    <section class="preview container">
      <h2>Popular courses</h2>
      <div class="grid">
        <?php if (!empty($popularCourses)): ?>
          <?php foreach ($popularCourses as $course): ?>
            <div class="card">
              <?php if (!empty($course['image'])): ?>
                <div class="thumb">
                  <img src="<?php echo htmlspecialchars($course['image']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" style="width: 100%; height: auto;">
                </div>
              <?php endif; ?>
              <h3><?php echo htmlspecialchars($course['title']); ?></h3>
              <a href="cdetail.php?id=<?php echo $course['id']; ?>" style="text-decoration: none;">
                <button style="padding: 8px 16px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">View course</button>
              </a>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No courses available at the moment.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="site-footer">
  <div class="footer-container">

    <div class="footer-col">
      <h4>Ifada</h4>
      <p>Your trusted platform for learning practical, project-based skills.</p>
    </div>

    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="hpage.php">Home</a></li>
        <li><a href="courses.php">Courses</a></li>
        <li><a href="FAQ.html">FAQ</a></li>
        <li><a href="login.php">Sign in</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Contact</h4>
      <ul>
        <li>Email: support@Ifada.com</li>
        <li>Phone: +1 234 567 890</li>
        <li>Address: 123 Learning Street</li>
      </ul>

      <div class="social-links">
        <a href="#" aria-label="Facebook">📘</a>
        <a href="#" aria-label="Twitter">🐦</a>
        <a href="#" aria-label="Instagram">📸</a>
      </div>
    </div>

  </div>

  <div class="footer-bottom">
    © 2025 Ifada — Learn at your pace
  </div>
</footer>

</body>
</html>

<?php
$conn->close();
?>
