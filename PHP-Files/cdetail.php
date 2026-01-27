<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Get cart count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Get course ID from URL
$courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($courseId <= 0) {
    die("Invalid course ID.");
}

// Fetch course details from database
$sql = "SELECT id, title, description, image, price FROM courses WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $courseId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Course not found.");
}

$course = $result->fetch_assoc();
$stmt->close();

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

// Check if user owns this course
$isOwned = false;
if ($isLoggedIn) {
    $sqlOwned = "SELECT DISTINCT c.id FROM courses c
                 JOIN sessions s ON c.id = s.course_id
                 JOIN enrollments e ON s.id = e.session_id
                 JOIN payments p ON e.id = p.enrollment_id
                 WHERE c.id = ? AND e.user_id = ? AND p.status = 'paid'";
    $stmtOwned = $conn->prepare($sqlOwned);
    $stmtOwned->bind_param('ii', $courseId, $_SESSION['user_id']);
    $stmtOwned->execute();
    $resultOwned = $stmtOwned->get_result();
    $isOwned = $resultOwned->num_rows > 0;
    $stmtOwned->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Course Detail</title>
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        .hero-section {
            background: linear-gradient(180deg, rgba(37,99,235,0.08), transparent 70%);
            padding: 40px 16px;
        }

        .hero-content {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .hero-image {
            width: 100%;
            height: 320px;
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-text h1 {
            margin: 0 0 16px;
            font-size: 2rem;
            color: #0b1220;
        }

        .hero-text p {
            margin: 12px 0;
            color: #6b7280;
            line-height: 1.6;
            font-size: 1rem;
        }

        .price-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .price-tag {
            font-size: 2.2rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .btn-enroll {
            flex: 1;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-enroll:hover {
            background: #1e40af;
        }

        .btn-cart {
            flex: 1;
            padding: 12px 20px;
            background: white;
            color: #28a745;
            border: 2px solid #28a745;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cart:hover {
            background: #f0fdf4;
        }

        .btn-owned {
            flex: 1;
            padding: 12px 20px;
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .course-details-section {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .main-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 8px 18px rgba(16,24,40,0.06);
        }

        .main-content h2 {
            margin: 0 0 16px;
            font-size: 1.4rem;
            color: #0b1220;
        }

        .main-content p {
            margin: 0 0 20px;
            color: #6b7280;
            line-height: 1.6;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 8px 18px rgba(16,24,40,0.06);
        }

        .info-card h4 {
            margin: 0 0 12px;
            font-size: 1.05rem;
            color: #0b1220;
        }

        .info-card p {
            margin: 8px 0;
            color: #6b7280;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .hero-text h1 {
                font-size: 1.5rem;
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
                <?php if ($isLoggedIn): ?>
                    <li><a href="dashboard.php" class="btn small">Dashboard</a></li>
                    <li><a href="cart.php" class="btn small" style="position: relative; background-color: #0864c5ff; color: white;">
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

    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-image">
                <?php if (!empty($course['image'])): ?>
                    <img src="<?php echo htmlspecialchars(getImagePath($course['image'])); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                <?php else: ?>
                    <span style="font-size: 80px;">📖</span>
                <?php endif; ?>
            </div>
            <div class="hero-text">
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                <p><?php echo htmlspecialchars($course['description']); ?></p>
                <div class="price-section">
                    <div class="price-tag">$<?php echo number_format($course['price'], 2); ?></div>
                    <div class="action-buttons">
                        <?php if ($isOwned): ?>
                            <button class="btn-owned" disabled>✓ Owned</button>
                        <?php else: ?>
                            <button class="btn-enroll" onclick="location.href='paiment.php?course_id=<?php echo $course['id']; ?>'">Enroll Now</button>
                            <form method="POST" action="cart.php" style="flex: 1;">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" class="btn-cart">Add to Cart</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="course-details-section">
        <div class="details-grid">
            <article class="main-content">
                <h2>About This Course</h2>
                <p><?php echo htmlspecialchars($course['description']); ?></p>
            </article>

            <aside class="sidebar">
                <div class="info-card">
                    <h4>📚 Course Details</h4>
                    <p><strong>Format:</strong> Self-paced online</p>
                    <p><strong>Level:</strong> Beginner to Intermediate</p>
                    <p><strong>Access:</strong> Lifetime access</p>
                </div>
            </aside>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-col">
                <h4>Ifada</h4>
                <p>Your trusted platform for learning practical, project-based skills.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="homePage.php">Home</a></li>
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
