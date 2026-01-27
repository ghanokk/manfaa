<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Get cart count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Check if user has instructor or admin role
$canCreateCourse = false;
if ($isLoggedIn && isset($_SESSION['user_role'])) {
    $userRole = $_SESSION['user_role'];
    // Handle both single role (string) and multiple roles (array)
    if (is_array($userRole)) {
        $canCreateCourse = in_array('instructor', $userRole) || in_array('admin', $userRole);
    } else {
        $canCreateCourse = ($userRole === 'instructor' || $userRole === 'admin');
    }
}

// Build SQL query
$sql = "
    SELECT 
        c.id,
        c.title,
        c.image,
        c.price,
        c.created_by,
        u.name AS creator_name
    FROM courses c
    JOIN users u ON c.created_by = u.id
    ORDER BY c.title
";


// Prepare and execute query
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

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

// Fetch user's owned courses (if logged in)
$ownedCourses = [];
if ($isLoggedIn) {
    $enrolledSql = "SELECT DISTINCT c.id
                    FROM courses c
                    INNER JOIN sessions s ON c.id = s.course_id
                    INNER JOIN enrollments e ON s.id = e.session_id
                    INNER JOIN payments p ON e.id = p.enrollment_id
                    WHERE e.user_id = ? AND p.status = 'paid'";
    $enrolledStmt = $conn->prepare($enrolledSql);
    if ($enrolledStmt) {
        $enrolledStmt->bind_param('i', $_SESSION['user_id']);
        $enrolledStmt->execute();
        $enrolledResult = $enrolledStmt->get_result();
        while ($row = $enrolledResult->fetch_assoc()) {
            $ownedCourses[] = $row['id'];
        }
        $enrolledStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Courses</title>
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        * {
            --primary-blue: #2563eb;
            --primary-purple: #9a73f7;
            --primary-pink: #ec4899;
            --primary-orange: #f97316;
            --primary-green: #10b981;
            --primary-red: #ef4444;
            --primary-cyan: #06b6d4;
            --primary-amber: #f59e0b;
        }

        .courses-hero {
            background: linear-gradient(180deg, rgba(102, 126, 234, 0.08), transparent 60%);
            padding: 40px 16px 20px;
            text-align: center;
            
        }

        .courses-hero h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 10px;
            font-weight: 700;
        }

        .courses-hero p {
            color: #6b7280;
            margin: 0;
            font-size: 1.1rem;
        }

        .courses-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 16px;
            
        }

        .courses-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .courses-header .course-count {
            color: #6b7280;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .courses-header .btn-create {
            padding: 16px 32px;
            background: linear-gradient(135deg, var(--primary-orange), var(--primary-amber));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.25);
        }

        .courses-header .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(249, 115, 22, 0.35);
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.36);
            transition: all 0.3s ease;
            border-top: 5px solid var(--primary-blue);
        }

        .course-card:nth-child(even) { border-top-color: var(--primary-purple); }
        .course-card:nth-child(3n) { border-top-color: var(--primary-pink); }
        .course-card:nth-child(4n) { border-top-color: var(--primary-orange); }


        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.73);
        }

        .course-card-image {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .course-card:nth-child(even) .course-card-image {
            background: linear-gradient(135deg, #f093fb, #f5576c, #ffa500);
        }

        .course-card:nth-child(3n) .course-card-image {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .course-card:nth-child(4n) .course-card-image {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .course-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .course-card-content {
            padding: 24px;
        }

        .course-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0b1220;
            margin: 0 0 8px;
            line-height: 1.4;
        }

        .course-card-creator {
            font-size: 0.85rem;
            font-weight: 500;
            color: #6b7280;
            margin: 0 0 16px;
        }

        .course-card-price {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 12px 0 16px;
        }

        .course-card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }

        .course-card-actions a,
        .course-card-actions form {
            display: contents;
        }

        .course-card-actions a button,
        .course-card-actions form button {
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan));
            color: white;
            text-decoration: none;
            padding: 14px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: all 0.3s ease;
            display: block;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .btn-cart {
            background: white;
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-cart:hover {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.1));
            transform: translateY(-2px);
        }

        .btn-owned {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-cyan));
            color: white;
            border: none;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            cursor: default;
        }

        .btn-owned:hover {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-cyan));
            transform: none;
        }
        
            color: #28a745;
            margin: 12px 0;
        }

        .course-card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }

        .course-card-actions a,
        .course-card-actions form {
            display: contents;
        }

        .course-card-actions a button,
        .course-card-actions form button {
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view {
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .btn-view:hover {
            background: #1e40af;
        }

        .btn-cart {
            background: white;
            color: #28a745;
            border: 2px solid #28a745;
        }

        .btn-cart:hover {
            background: #f0fdf4;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .courses-header {
                flex-direction: column;
                align-items: stretch;
            }

            .courses-header .btn-create {
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
                <li><a href="hpage.php">Home</a></li>
                <li><a href="event.php">Events</a></li>
                <li><a href="FAQ.html">FAQ</a></li>
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

    <section class="courses-hero">
        <h1>All Courses</h1>
        <p><?php echo $result->num_rows; ?> courses available</p>
    </section>

    <div class="courses-container">
        <div class="courses-header">
            <span class="course-count">Showing <?php echo $result->num_rows; ?> courses</span>
            <?php if ($canCreateCourse): ?>
                <a href="create_course.php" class="btn-create">+ Create Course</a>
            <?php endif; ?>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="courses-grid">
                <?php while ($course = $result->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-card-image">
                            <?php if (!empty($course['image'])): ?>
                                <img src="<?php echo htmlspecialchars(getImagePath($course['image'])); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <?php else: ?>
                                <span style="font-size: 60px;">📖</span>
                            <?php endif; ?>
                        </div>
                        <div class="course-card-content">
                            <h3 class="course-card-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <h3 class="course-card-creator">created by =<?php echo htmlspecialchars($course['creator_name']); ?></h3>
                            <div class="course-card-price">$<?php echo number_format($course['price'], 2); ?></div>
                            <div class="course-card-actions">
                                <a href="cdetail.php?id=<?php echo $course['id']; ?>"><button class="btn-view">View Course</button></a>
                                <?php if (in_array($course['id'], $ownedCourses)): ?>
                                    <button class="btn-owned" disabled>✓ Owned</button>
                                <?php else: ?>
                                    <form method="POST" action="cart.php" style="display: contents;">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="btn-cart">Add to Cart</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <p>No courses available at the moment. Check back soon!</p>
            </div>
        <?php endif; ?>
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
$result->free_result();
$stmt->close();
$conn->close();
?>
