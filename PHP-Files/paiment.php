<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Initialize cart in session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// Fetch user email
$userSql = "SELECT email FROM users WHERE id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// Fetch cart items from database
$cartItems = [];
$subtotal = 0;

if (!empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $cartSql = "SELECT id, title, price FROM courses WHERE id IN ($placeholders)";
    $cartStmt = $conn->prepare($cartSql);
    $cartStmt->bind_param(str_repeat('i', count($_SESSION['cart'])), ...$_SESSION['cart']);
    $cartStmt->execute();
    $cartResult = $cartStmt->get_result();
    
    while ($item = $cartResult->fetch_assoc()) {
        $cartItems[] = $item;
        $subtotal += floatval($item['price']);
    }
    $cartStmt->close();
}

// Calculate totals
$shippingCost = 0;
$taxRate = 0.10; // 10% tax
$tax = round($subtotal * $taxRate, 2);
$total = round($subtotal + $tax + $shippingCost, 2);

// Handle payment submission
$paymentSuccess = false;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    $cardName = trim($_POST['cardname'] ?? '');
    $cardNumber = trim(str_replace(' ', '', $_POST['cardnumber'] ?? ''));
    $exp = trim($_POST['exp'] ?? '');
    $cvv = trim($_POST['cvv'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Basic validation
    if (empty($cardName) || empty($cardNumber) || empty($exp) || empty($cvv) || empty($email)) {
        $errorMessage = 'All fields are required.';
    } elseif (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        $errorMessage = 'Invalid card number.';
    } elseif (strlen($cvv) < 3 || strlen($cvv) > 4) {
        $errorMessage = 'Invalid CVV.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid email address.';
    } else {
        // Process payment (in a real scenario, you'd integrate with a payment gateway like Stripe)
        // For now, we'll simulate a successful payment
        
        // Create enrollments for all courses in cart
        $enrollmentSuccess = true;
        $currentDate = date('Y-m-d H:i:s');
        
        foreach ($_SESSION['cart'] as $courseId) {
            $enrollSql = "INSERT INTO enrollments (user_id, course_id, enrolled_date) VALUES (?, ?, ?)";
            $enrollStmt = $conn->prepare($enrollSql);
            if (!$enrollStmt) {
                $enrollmentSuccess = false;
                break;
            }
            $enrollStmt->bind_param('iis', $userId, $courseId, $currentDate);
            if (!$enrollStmt->execute()) {
                $enrollmentSuccess = false;
                $enrollStmt->close();
                break;
            }
            $enrollStmt->close();
        }
        
        if ($enrollmentSuccess) {
            // Clear the cart after successful payment
            $_SESSION['cart'] = [];
            $paymentSuccess = true;
        } else {
            $errorMessage = 'Payment processing failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Payment</title>
  <link rel="stylesheet" href="../styles/Paiment.css">
  <style>
    .success-message {
      background-color: #d4edda;
      color: #155724;
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      border: 1px solid #c3e6cb;
    }
    .error-message {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      border: 1px solid #f5c6cb;
    }
    .success-actions {
      text-align: center;
      margin-top: 20px;
    }
    .success-actions a {
      display: inline-block;
      padding: 12px 30px;
      background: #28a745;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      transition: 0.2s;
      margin: 0 10px;
    }
    .success-actions a:hover {
      background: #218838;
    }
  </style>
</head>
<body>
  <header class="site-header">
    <nav class="nav">
      <div class="brand">Ifada</div>
      <a class="back" href="cart.php">← Back to Cart</a>
    </nav>
  </header>

  <main class="wrap">
    <?php if ($paymentSuccess): ?>
      <section class="payment-card">
        <div class="success-message">
          <h2 style="margin-top: 0;">✓ Payment Successful!</h2>
          <p>Thank you for your purchase. Your courses are now available in your profile.</p>
        </div>
        <div class="success-actions">
          <a href="profile.php">View My Courses</a>
          <a href="courses.php">Browse More Courses</a>
        </div>
      </section>
    <?php else: ?>
      <section class="payment-card">
        <h2>Complete your payment</h2>

        <?php if (!empty($errorMessage)): ?>
          <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <form class="payment-form" method="post" autocomplete="on">
          <label class="field">
            <span class="label">Cardholder name</span>
            <input type="text" name="cardname" placeholder="Full name" required>
          </label>

          <label class="field">
            <span class="label">Card number</span>
            <input type="text" name="cardnumber" inputmode="numeric" pattern="[0-9\s]{13,19}" placeholder="1234 5678 9012 3456" maxlength="19" required>
          </label>

          <div class="row">
            <label class="field small">
              <span class="label">Expiry</span>
              <input type="text" name="exp" placeholder="MM/YY" maxlength="5" required>
            </label>

            <label class="field small">
              <span class="label">CVV</span>
              <input type="text" name="cvv" inputmode="numeric" maxlength="4" placeholder="123" required>
            </label>
          </div>

          <label class="field">
            <span class="label">Billing email</span>
            <input type="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
          </label>

          <div class="summary">
            <h3>Order summary</h3>
            <?php foreach ($cartItems as $item): ?>
              <div class="line">
                <span><?php echo htmlspecialchars($item['title']); ?></span>
                <span class="price">$<?php echo number_format($item['price'], 2); ?></span>
              </div>
            <?php endforeach; ?>
            <div class="line">
              <span>Subtotal</span>
              <span class="price">$<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <?php if ($tax > 0): ?>
              <div class="line">
                <span>Tax (10%)</span>
                <span class="price">$<?php echo number_format($tax, 2); ?></span>
              </div>
            <?php endif; ?>
            <?php if ($shippingCost > 0): ?>
              <div class="line">
                <span>Shipping</span>
                <span class="price">$<?php echo number_format($shippingCost, 2); ?></span>
              </div>
            <?php endif; ?>
            <div class="line total">
              <span>Total</span>
              <span class="price">$<?php echo number_format($total, 2); ?></span>
            </div>
          </div>

          <button class="btn pay" type="submit">Buy Now</button>
        </form>
      </section>
    <?php endif; ?>
  </main>

  <footer class="site-footer">
    <small>© Ifada — All rights reserved</small>
  </footer>
</body>
</html>

<?php
$conn->close();
?>
