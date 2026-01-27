<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? '';

// Get cart count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Fetch popular courses (limit to 6)
$popularSql = "SELECT id, title, image, price FROM courses LIMIT 6";
$popularResult = $conn->query($popularSql);
$popularCourses = [];
if ($popularResult) {
    while ($row = $popularResult->fetch_assoc()) {
        $popularCourses[] = $row;
    }
}

// Helper function to get proper image path
function getImagePath($imagePath) {
    if (empty($imagePath)) {
        return '';
    }
    if (strpos($imagePath, 'http') === 0 || strpos($imagePath, '/') === 0) {
        return $imagePath;
    }
    if (strpos($imagePath, '../') !== 0 && strpos($imagePath, './') !== 0) {
        return '../images/' . $imagePath;
    }
    return $imagePath;
}

// Define color classes for cards
$colors = [
    ['border' => '#2563eb', 'bg' => 'rgba(37, 99, 235, 0.05)', 'button' => 'linear-gradient(135deg, #2563eb, #3b82f6)'],
    ['border' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.05)', 'button' => 'linear-gradient(135deg, #8b5cf6, #a78bfa)'],
    ['border' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.05)', 'button' => 'linear-gradient(135deg, #ec4899, #f472b6)'],
    ['border' => '#f97316', 'bg' => 'rgba(249, 115, 22, 0.05)', 'button' => 'linear-gradient(135deg, #f97316, #fb923c)'],
    ['border' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.05)', 'button' => 'linear-gradient(135deg, #10b981, #34d399)'],
    ['border' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.05)', 'button' => 'linear-gradient(135deg, #06b6d4, #22d3ee)']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HomePage - Ifada</title>
  <link rel="stylesheet" href="../styles/HomePage.css">
  <style>
    :root {
      --primary-blue: #2563eb;
      --primary-purple: #8b5cf6;
      --primary-pink: #ec4899;
      --primary-orange: #f97316;
      --primary-green: #10b981;
      --primary-red: #ef4444;
      --primary-cyan: #06b6d4;
      --primary-amber: #f59e0b;
    }

    body {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .home-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 20px;
      position: relative;
      overflow: hidden;
    }

    .hero-section {
      background: linear-gradient(135deg, #2563eb 0%, #8b5cf6 50%, #ec4899 100%);
      color: white;
      padding: 80px 40px;
      border-radius: 20px;
      text-align: center;
      margin-bottom: 60px;
      box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
    }

    .hero-section h1 {
      font-size: 3.5rem;
      margin: 0 0 20px 0;
      font-weight: 800;
      letter-spacing: -1px;
    }

    .hero-section p {
      font-size: 1.3rem;
      margin: 0 0 30px 0;
      opacity: 0.95;
      line-height: 1.6;
    }

    .hero-buttons {
      display: flex;
      gap: 20px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-primary {
      padding: 16px 40px;
      background: white;
      color: #2563eb;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      font-size: 1.1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
    }

    .btn-secondary {
      padding: 16px 40px;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 2px solid white;
      border-radius: 10px;
      font-weight: 700;
      font-size: 1.1rem;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    .section-title {
      font-size: 2.5rem;
      font-weight: 800;
      color: #0b1220;
      margin: 0 0 40px 0;
      text-align: center;
    }

    .courses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 60px;
      position: relative;
    }

    .course-card {
      background: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(16, 24, 40, 0.08);
      transition: all 0.3s ease;
      border-top: 5px solid;
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .course-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(16, 24, 40, 0.57);
    }

    .course-image {
      width: 100%;
      height: 140px;
      background: linear-gradient(135deg, #e0e7ff, #f3e8ff);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      font-size: 60px;
    }

    .course-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .course-content {
      padding: 18px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .course-title {
      font-size: 1rem;
      font-weight: 700;
      color: #0b1220;
      margin: 0 0 10px 0;
      line-height: 1.3;
    }

    .course-price {
      font-size: 1.4rem;
      font-weight: 800;
      color: #28a745;
      margin: 8px 0;
    }

    .course-actions {
      display: flex;
      gap: 12px;
      margin-top: auto;
    }

    .btn-view {
      flex: 1;
      padding: 10px 16px;
      background: linear-gradient(135deg, #2563eb, #3b82f6);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-view:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
    }

    /* Decorative colored spots */
    .colored-spot {
      position: absolute;
      border-radius: 50%;
      opacity: 0.6;
      mix-blend-mode: multiply;
      pointer-events: none;
    }

    .spot-blue {
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(37, 99, 235, 0.3), transparent);
      top: -100px;
      left: -100px;
    }

    .spot-purple {
      width: 250px;
      height: 250px;
      background: radial-gradient(circle, rgba(139, 92, 246, 0.3), transparent);
      top: 200px;
      right: -80px;
    }

    .spot-pink {
      width: 280px;
      height: 280px;
      background: radial-gradient(circle, rgba(236, 72, 153, 0.2), transparent);
      bottom: 100px;
      left: -100px;
    }

    .spot-orange {
      width: 220px;
      height: 220px;
      background: radial-gradient(circle, rgba(249, 115, 22, 0.25), transparent);
      bottom: 50px;
      right: -70px;
    }

    .spot-green {
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(16, 185, 129, 0.2), transparent);
      top: 50%;
      right: 100px;
    }

    .features-section {
      background: white;
      padding: 60px 40px;
      border-radius: 20px;
      margin: 60px 0;
      box-shadow: 0 8px 24px rgba(16, 24, 40, 0.08);
      position: relative;
      overflow: hidden;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      margin-top: 40px;
    }

    .feature-card {
      text-align: center;
    }

    .feature-icon {
      font-size: 4rem;
      margin-bottom: 20px;
    }

    .feature-card h3 {
      font-size: 1.3rem;
      font-weight: 700;
      color: #0b1220;
      margin: 0 0 12px 0;
    }

    .feature-card p {
      color: #6b7280;
      line-height: 1.6;
      margin: 0;
    }

    @media (max-width: 768px) {
      .hero-section {
        padding: 40px 20px;
      }

      .hero-section h1 {
        font-size: 2.2rem;
      }

      .hero-section p {
        font-size: 1rem;
      }

      .courses-grid {
        grid-template-columns: 1fr;
      }

      .section-title {
        font-size: 1.8rem;
      }

      .hero-buttons {
        flex-direction: column;
      }

      .btn-primary, .btn-secondary {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <nav class="nav">
      <div class="brand">Ifada</div>
      <ul class="nav-links">
        <li><a href="homePage.php">Home</a></li>
        <li><a href="courses.php">Courses</a></li>
        <li><a href="event.php">Events</a></li>
        <li><a href="FAQ.html">FAQ</a></li>
        <?php if ($isLoggedIn): ?>
          <?php if (in_array('admin', (array)$userRole)): ?>
            <li><a href="admin_dash.php" class="btn small" style="background-color: #dc2626; color: white; margin: 0 10px;">🛡️ Admin</a></li>
          <?php endif; ?>
          <li><a href="dashboard.php" class="btn small">Dashboard</a></li>
          <li><a href="cart.php" class="btn small" style="margin-left: 30px; position: relative; background-color: #0864c5ff; color: white;">
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
    <div class="home-container">
      <!-- Decorative colored spots -->
      <div class="colored-spot spot-blue"></div>
      <div class="colored-spot spot-purple"></div>
      <div class="colored-spot spot-pink"></div>
      <div class="colored-spot spot-green"></div>
      <!-- Hero Section -->
      <section class="hero-section">
        <h1>Learn. Build. Grow.</h1>
        <p>Master practical skills with hands-on courses from industry experts</p>
        <div class="hero-buttons">
          <a href="courses.php" class="btn-primary">Browse Courses</a>
          <a href="FAQ.html" class="btn-secondary">Learn How It Works</a>
        </div>
      </section>

      <!-- Features Section -->
      <section class="features-section">
        <div class="colored-spot spot-orange"></div>
        <h2 class="section-title">Why Choose Ifada?</h2>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">📚</div>
            <h3>Curated Curriculum</h3>
            <p>Short, focused courses designed for real-world projects and practical skills</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🧑‍🏫</div>
            <h3>Expert Instructors</h3>
            <p>Learn from industry practitioners with hands-on experience</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🚀</div>
            <h3>Hands-On Projects</h3>
            <p>Build a professional portfolio while mastering new skills</p>
          </div>
        </div>
      </section>

      <!-- Popular Courses Section -->
      <h2 class="section-title">Popular Courses</h2>
      <div class="courses-grid">
        <?php if (!empty($popularCourses)): ?>
          <?php foreach ($popularCourses as $index => $course): ?>
            <?php $color = $colors[$index % count($colors)]; ?>
            <div class="course-card" style="border-top-color: <?php echo $color['border']; ?>;">
              <div class="course-image">
                <?php if (!empty($course['image'])): ?>
                  <img src="<?php echo htmlspecialchars(getImagePath($course['image'])); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                <?php else: ?>
                  <span>📖</span>
                <?php endif; ?>
              </div>
              <div class="course-content">
                <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                <div class="course-price">$<?php echo number_format($course['price'], 2); ?></div>
                <div class="course-actions">
                  <a href="cdetail.php?id=<?php echo $course['id']; ?>" class="btn-view" style="background: <?php echo $color['button']; ?>;">
                    View Course
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="grid-column: 1/-1; text-align: center; color: #6b7280; font-size: 1.1rem;">No courses available at the moment.</p>
        <?php endif; ?>
      </div>
    </div>
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

