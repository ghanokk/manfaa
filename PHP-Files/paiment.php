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

// Fetch cart items from database with their sessions
$cartItems = [];
$subtotal = 0;

if (!empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $cartSql = "SELECT c.id, c.title, c.price, s.id as session_id, s.start_date, s.end_date 
                FROM courses c 
                LEFT JOIN sessions s ON c.id = s.course_id 
                WHERE c.id IN ($placeholders)
                ORDER BY c.id, s.start_date";
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
        // Process fake payment (simulated successful payment)
        // In a real scenario, you'd integrate with Stripe, PayPal, etc.
        
        $paymentMethod = 'Credit Card - ' . substr($cardNumber, -4);
        $enrollmentSuccess = true;
        $totalPaymentAmount = 0;
        
        foreach ($cartItems as $item) {
            $sessionId = $item['session_id'];
            
            // If course doesn't have a session, create one automatically
            if (empty($sessionId)) {
                $startDate = date('Y-m-d'); // Today
                $endDate = date('Y-m-d', strtotime('+30 days')); // 30 days from now
                $capacity = 50; // Default capacity
                
                $createSessionSql = "INSERT INTO sessions (course_id, start_date, end_date, capacity) 
                                     VALUES (?, ?, ?, ?)";
                $createSessionStmt = $conn->prepare($createSessionSql);
                if (!$createSessionStmt) {
                    $enrollmentSuccess = false;
                    $errorMessage = 'Failed to create session for course.';
                    break;
                }
                $createSessionStmt->bind_param('issi', $item['id'], $startDate, $endDate, $capacity);
                if (!$createSessionStmt->execute()) {
                    $enrollmentSuccess = false;
                    $errorMessage = 'Failed to create session for course.';
                    $createSessionStmt->close();
                    break;
                }
                $sessionId = $createSessionStmt->insert_id;
                $createSessionStmt->close();
            }
            
            // Create enrollment for the session
            $enrollStatus = 'confirmed';
            $enrollSql = "INSERT INTO enrollments (user_id, session_id, status) VALUES (?, ?, ?)";
            $enrollStmt = $conn->prepare($enrollSql);
            if (!$enrollStmt) {
                $enrollmentSuccess = false;
                $errorMessage = 'Failed to create enrollment.';
                break;
            }
            $enrollStmt->bind_param('iss', $userId, $sessionId, $enrollStatus);
            if (!$enrollStmt->execute()) {
                $enrollmentSuccess = false;
                $errorMessage = 'Failed to create enrollment.';
                $enrollStmt->close();
                break;
            }
            $enrollmentId = $enrollStmt->insert_id;
            $enrollStmt->close();
            
            // Create payment record for this enrollment
            $coursePrice = floatval($item['price']);
            $paymentSql = "INSERT INTO payments (enrollment_id, amount, method, status) VALUES (?, ?, ?, 'paid')";
            $paymentStmt = $conn->prepare($paymentSql);
            if (!$paymentStmt) {
                $enrollmentSuccess = false;
                $errorMessage = 'Failed to process payment.';
                break;
            }
            $paymentStmt->bind_param('ids', $enrollmentId, $coursePrice, $paymentMethod);
            if (!$paymentStmt->execute()) {
                $enrollmentSuccess = false;
                $errorMessage = 'Failed to process payment.';
                $paymentStmt->close();
                break;
            }
            $paymentStmt->close();
            $totalPaymentAmount += $coursePrice;
        }
        
        if ($enrollmentSuccess) {
            // Clear the cart after successful payment
            $_SESSION['cart'] = [];
            $paymentSuccess = true;
        } else {
            if (empty($errorMessage)) {
                $errorMessage = 'Payment processing failed. Please try again.';
            }
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
    * {
      --primary-blue: #2563eb;
      --primary-purple: #8b5cf6;
      --primary-pink: #ec4899;
      --primary-orange: #f97316;
      --primary-green: #10b981;
      --primary-red: #ef4444;
      --primary-cyan: #06b6d4;
      --primary-amber: #f59e0b;
    }

    .success-message {
      background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
      color: #166534;
      padding: 24px;
      border-radius: 12px;
      margin-bottom: 20px;
      border-left: 5px solid var(--primary-green);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .success-message h2 {
      margin-top: 0;
      font-size: 1.3rem;
      font-weight: 700;
    }

    .success-message p {
      margin: 10px 0 0 0;
      font-size: 1rem;
    }

    .error-message {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #7f1d1d;
      padding: 24px;
      border-radius: 12px;
      margin-bottom: 20px;
      border-left: 5px solid var(--primary-red);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
      font-weight: 600;
    }

    .success-actions {
      text-align: center;
      margin-top: 30px;
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .success-actions a {
      display: inline-block;
      padding: 18px 40px;
      background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan));
      color: white;
      text-decoration: none;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1rem;
      transition: all 0.3s ease;
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.25);
    }

    .success-actions a:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
    }

    .success-actions a:nth-child(2) {
      background: linear-gradient(135deg, var(--primary-green), var(--primary-cyan));
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.25);
    }

    .success-actions a:nth-child(2):hover {
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
    }
  </style>
</head>
<body>
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
