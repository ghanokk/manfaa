<?php 
session_start();
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
        <li><a href="courses.php">Courses</a></li>
        <li><a href="faq.php">FAQ</a></li>
        <li><a href="login.php" class="btn small">Sign in</a></li>
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
          <a href="faq.php" class="btn ghost">How it works</a>
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
        <div class="card">
          <div class="thumb">HTML & CSS</div>
          <p class="meta">Beginner • 4 weeks</p>
        </div>
        <div class="card">
          <div class="thumb">JavaScript</div>
          <p class="meta">Intermediate • 6 weeks</p>
        </div>
        <div class="card">
          <div class="thumb">React</div>
          <p class="meta">Intermediate • 5 weeks</p>
        </div>
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
        <li><a href="courses.php">Courses</a></li>
        <li><a href="faq.php">FAQ</a></li>
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