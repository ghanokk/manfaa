<?php
session_start();
include 'db.php';

$message = '';
$message_type = '';

// Check if there's a message in session from form submission
if (isset($_SESSION['reset_message'])) {
    $message = $_SESSION['reset_message'];
    $message_type = $_SESSION['reset_message_type'];
    unset($_SESSION['reset_message'], $_SESSION['reset_message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $_SESSION['reset_message'] = "Please enter your email address.";
        $_SESSION['reset_message_type'] = 'error';
    } else {

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $user_name = $user['name'];

            // Invalidate old tokens
            $invalidate = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?");
            $invalidate->bind_param('i', $user_id);
            $invalidate->execute();
            $invalidate->close();

            // Generate token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = password_hash($rawToken, PASSWORD_BCRYPT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Save token
            $insert = $conn->prepare(
                "INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (?, ?, ?)"
            );
            $insert->bind_param('iss', $user_id, $tokenHash, $expiresAt);
            $insert->execute();
            $insert->close();

            // Reset link
            $reset_link = "http://localhost/ifada2/PHP-Files/reset_password.php?token=" . $rawToken;

            // Email body
            $body  = "Hello $user_name,<br><br>";
            $body .= "Click the link below to reset your password:<br><br>";
            $body .= "<a href='$reset_link'>$reset_link</a><br><br>";
            $body .= "This link expires in 15 minutes.<br><br>";
            $body .= "If you did not request this, ignore this message.<br><br>";
            $body .= "— IFADA Team";

            // Prepare email
            $subject = "IFADA Password Reset Request";
            $headers = "From: no-reply@ifada.com\r\n";
            $headers .= "Reply-To: no-reply@ifada.com\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            // Send email
            if (mail($email, $subject, $body, $headers)) {
                $_SESSION['reset_message'] = "If this email exists, a password reset link has been sent.";
                $_SESSION['reset_message_type'] = 'success';
            } else {
                $_SESSION['reset_message'] = "There was an error sending the reset email. Please try again later.";
                $_SESSION['reset_message_type'] = 'error';
            }
        }

        $stmt->close();
        header('Location: forgot.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../styles/auth.css">
    <style>
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<a href="login.php" class="back-btn">← Back to login</a>

<div class="auth-wrapper">
    <div class="auth-box">
        <h2>Forgot Password</h2>

        <?php if (!empty($message)): ?>
            <div class="<?= $message_type === 'success' ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required>
            <button type="submit" class="btn primary">Generate Reset Link</button>
        </form>
    </div>
</div>

</body>
</html>