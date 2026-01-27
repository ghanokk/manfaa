<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Initialize cart in session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add to cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!$isLoggedIn) {
        // Redirect to login if not logged in
        header('Location: login.php');
        exit;
    }
    
    $courseId = intval($_POST['course_id']);
    
    // Check if course already in cart
    if (!in_array($courseId, $_SESSION['cart'])) {
        $_SESSION['cart'][] = $courseId;
    }
    
    // Redirect back to courses page
    header('Location: courses.php');
    exit;
}

// Handle remove from cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    $courseId = intval($_POST['course_id']);
    
    // Remove from cart
    $_SESSION['cart'] = array_filter($_SESSION['cart'], function($id) use ($courseId) {
        return $id !== $courseId;
    });
    $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
    
    // Redirect back to cart page
    header('Location: cart.php');
    exit;
}

// Fetch cart items from database using session cart array
$cartItems = [];
$totalPrice = 0;

if ($isLoggedIn && !empty($_SESSION['cart'])) {
    // Get all courses in cart
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $cartSql = "SELECT id, title, price, image FROM courses WHERE id IN ($placeholders)";
    $cartStmt = $conn->prepare($cartSql);
    
    if ($cartStmt) {
        $cartStmt->bind_param(str_repeat('i', count($_SESSION['cart'])), ...$_SESSION['cart']);
        $cartStmt->execute();
        $cartResult = $cartStmt->get_result();
        
        while ($item = $cartResult->fetch_assoc()) {
            $item['cart_id'] = $item['id']; // Use course id as cart_id
            $cartItems[] = $item;
            $totalPrice += $item['price'];
        }
        $cartStmt->close();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="../styles/Courses.css">
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        .cart-wrapper {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 16px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }

        .cart-list {
            background: #dfe5f3ff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .cart-header {
            padding: 24px;
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            color: white;
        }

        .cart-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-body {
            padding: 24px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 16px;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .cart-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.42);
            transform: translateY(-2px);
            background: #9ebcf8ff

        }

        .cart-item:last-child {
            margin-bottom: 0;
        }

        .cart-item-image {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-details h3 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
            color: #0b1220;
        }

        .cart-item-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 10px;
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
        }

        .btn-remove {
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }

        .btn-remove:hover {
            background-color: #c82333;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 24px;
            color: #6b7280;
        }

        .empty-cart-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-cart p {
            margin: 10px 0;
            font-size: 1.1rem;
        }

        .empty-cart a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .empty-cart a:hover {
            background: #1e40af;
        }

        .summary {
            background: white;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary h3 {
            margin: 0 0 20px 0;
            font-size: 1.3rem;
            color: #0b1220;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .summary-line.total {
            padding: 15px 0 0 0;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            font-size: 1.2rem;
            font-weight: 700;
            color: #0b1220;
        }

        .summary-line .price {
            font-weight: 600;
        }

        .summary-line.total .price {
            color: #28a745;
            font-size: 1.4rem;
        }

        .btn-checkout {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }

        .btn-continue {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            background: white;
            color: #2563eb;
            border: 2px solid #2563eb;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-continue:hover {
            background: #f0f4ff;
        }

        .login-prompt {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .login-prompt a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .login-prompt a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .cart-wrapper {
                grid-template-columns: 1fr;
            }

            .cart-item {
                grid-template-columns: 100px 1fr;
                gap: 15px;
            }

            .cart-item-image {
                width: 100px;
                height: 100px;
            }

            .cart-item-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: flex-end;
            }

            .summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <h1>🛒 Shopping Cart</h1>
        <nav class="navbar">
            <a class="link" href="homePage.php">Ifada</a>
            <form class="nav-search" role="search">
                <input type="search" placeholder="Search courses..." aria-label="Search courses">
            </form>
            <div class="nav-right">
                <?php if ($isLoggedIn): ?>
                    <a class="link" href="dashboard.php">Dashboard</a>
                    
                <?php else: ?>
                    <a class="link" href="login.php">Sign in</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="cart-wrapper">
        <section class="cart-list">
            <?php if (!$isLoggedIn): ?>
                <div class="login-prompt">
                    <p>You need to <a href="login.php">sign in</a> to view your cart.</p>
                </div>
            <?php elseif (empty($cartItems)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">📚</div>
                    <p>Your cart is empty</p>
                    <p style="font-size: 0.95rem;">Add some courses to get started!</p>
                    <a href="courses.php">Continue Shopping</a>
                </div>
            <?php else: ?>
                <div class="cart-header">
                    <h2>📚 Your Cart</h2>
                </div>
                <div class="cart-body">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item">
                            <?php if (!empty($item['image'])): ?>
                                <div class="cart-item-image">
                                    <img src="<?php echo htmlspecialchars(getImagePath($item['image'])); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                </div>
                            <?php else: ?>
                                <div class="cart-item-image" style="display: flex; align-items: center; justify-content: center; font-size: 2rem;">📖</div>
                            <?php endif; ?>
                            
                            <div class="cart-item-details">
                                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            </div>

                            <div class="cart-item-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_from_cart">
                                    <input type="hidden" name="course_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-remove">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($isLoggedIn && !empty($cartItems)): ?>
            <aside class="summary">
                <h3>Order Summary</h3>
                
                <div class="summary-line">
                    <span>Subtotal:</span>
                    <span class="price">$<?php echo number_format($totalPrice, 2); ?></span>
                </div>
                
                <div class="summary-line">
                    <span>Tax (10%):</span>
                    <span class="price">$<?php echo number_format($totalPrice * 0.10, 2); ?></span>
                </div>
                
                <div class="summary-line">
                    <span>Shipping:</span>
                    <span class="price">Free</span>
                </div>

                <div class="summary-line total">
                    <span>Total:</span>
                    <span class="price">$<?php echo number_format($totalPrice * 1.10, 2); ?></span>
                </div>

                <button class="btn-checkout" onclick="location.href='paiment.php'">Proceed to Checkout</button>
                <button class="btn-continue" onclick="location.href='courses.php'">Continue Shopping</button>
            </aside>
        <?php endif; ?>
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
