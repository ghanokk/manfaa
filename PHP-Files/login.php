<?php 
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        echo "Email and password are required.";
        exit;
    }

    // Use prepared statement
    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        echo "Error: {$conn->error}";
        exit;
    }

    if ($result->num_rows === 0) {
        echo "Invalid email or password.";
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // Verify password. Support legacy plaintext passwords by upgrading them to hashed.
    $stored = $row['password'];
    $password_ok = false;
    if (password_verify($password, $stored)) {
        $password_ok = true;
    } elseif ($password === $stored) {
        // legacy plaintext match — upgrade to hashed password
        $newhash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param('si', $newhash, $row['id']);
        $upd->execute();
        $upd->close();
        $password_ok = true;
    }

    if ($password_ok) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['name'];
        header('Location: dashboard.php');
        exit;
    } else {
        echo "Invalid email or password.";
        exit;
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In</title>
    <link rel="stylesheet" href="../styles/auth.css">
</head>
<body>
    
<a href="HomePage.html" class="back-btn">← Back to Home</a>


<div class="auth-wrapper">
    <div class="auth-box">
        <h2>Log In</h2>

        <form action="login.php" method="POST">
            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button class="btn primary">Log In</button>

            <p class="links">
                <a href="../HTML-Files/Forgot.html">Forgot Password?</a>
                <a href="Register.php">Create an account</a>
            </p>
        </form>
    </div>
</div>

</body>
</html>