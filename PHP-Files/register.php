<?php
session_start();
include 'db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $message = 'All fields are required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please provide a valid email address.';
        $message_type = 'error';
    } else {
        // check existing email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email); // means the value is treated as string
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = 'Email already registered. <a href="login.php">Sign in</a>';
            $message_type = 'error';
            $stmt->close();
        } else {
            $stmt->close();

            // hash and insert
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $ins->bind_param('sss', $name, $email, $hashed);
            if ($ins->execute()) {
                $message = 'Registration successful. <a href="login.php">Sign in</a>';
                $message_type = 'success';
            } 
            /* === This Step Is For Me For Debugging === */
            else {
                if ($ins->errno == 1062) {
                    $message = 'Database error: duplicate primary key detected. Ensure the users.id column is AUTO_INCREMENT: <code>ALTER TABLE users MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY;</code>';
                } else {
                    $message = 'Database error: ' . htmlspecialchars($ins->error);
                }
                $message_type = 'error';
            }
            $ins->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/auth.css">
    <title>Register</title>
    <style>
    .message { margin-bottom: 12px; padding: 8px; border-radius: 8px; text-decoration: none; }
    .message.success { background: #d1fae5; color: #065f46; text-decoration: none; }
    .message.error { background: #fee2e2; color: #7f1d1d; text-decoration: none; }
    </style>
</head>
<body>
    <a href="HomePage.html" class="back-btn">← Back to Home</a>

    <div class="auth-wrapper">
        <div class="auth-box">
            <h2>Sign Up</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <label>Name</label>
                <input type="text" name="name" required>

                <label>Email</label>
                <input type="email" name="email" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <button class="btn primary" type="submit" name="submit">Create Account</button>

                <p class="links">
                    <a href="signin.html">Already have an account?</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>