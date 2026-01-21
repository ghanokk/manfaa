<?php
session_start();
require 'db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $email = $_SESSION['email'] ;
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? 'student');

    if ($name === '' || $email === '' || $password === '') {
        $message = 'All fields are required.';
        $message_type = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
        $message_type = 'error';

    } else {

        /* =====================
           1️⃣ Check email exists
        ===================== */
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = 'Email already registered.';
            $message_type = 'error';
            $check->close();

        } else {
            $check->close();

            /* =====================
               2️⃣ Insert user
            ===================== */
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $ins = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $name, $email, $hashed);

            if ($ins->execute()) {

                $user_id = $conn->insert_id;

                /* =====================
                   3️⃣ Validate role
                ===================== */
                $allowed_roles = ['admin', 'instructor', 'student'];
                if (!in_array($role, $allowed_roles, true)) {
                    $role = 'student';
                }

                /* =====================
                   4️⃣ Get role_id
                ===================== */
                $rstmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
                $rstmt->bind_param("s", $role);
                $rstmt->execute();
                $rres = $rstmt->get_result();

                if ($rres->num_rows === 0) {
                    // role not found → create it
                    $insr = $conn->prepare("INSERT INTO roles (name) VALUES (?)");
                    $insr->bind_param("s", $role);
                    $insr->execute();
                    $role_id = $conn->insert_id;
                    $insr->close();
                } else {
                    $role_id = (int)$rres->fetch_assoc()['id'];
                }
                $rstmt->close();

                /* =====================
                   5️⃣ Insert user_roles
                ===================== */
                $ur = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $ur->bind_param("ii", $user_id, $role_id);
                $ur->execute();
                $ur->close();

                $message = 'Registration successful. You can now log in.';
                $message_type = 'success';

            } else {
                $message = 'Database error: ' . $ins->error;
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
    <a href="home.php" class="back-btn">← Back to Home</a>

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

                    <label>Role</label>
                    <select name="role" required>
                        <option value="student">Student</option>
                        <option value="instructor">Instructor</option>
                        <option value="admin">Admin</option>
                    </select>

                <button class="btn primary" type="submit" name="submit">Create Account</button>

                <p class="links">
                    <a href="signin.html">Already have an account?</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>