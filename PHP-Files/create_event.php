<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Check if user has instructor or admin role
$canCreateEvent = false;
if ($isLoggedIn && isset($_SESSION['user_role'])) {
    $userRole = $_SESSION['user_role'];
    if (is_array($userRole)) {
        $canCreateEvent = in_array('instructor', $userRole) || in_array('admin', $userRole);
    } else {
        $canCreateEvent = ($userRole === 'instructor' || $userRole === 'admin');
    }
}

// If user doesn't have permission, redirect
if (!$isLoggedIn || !$canCreateEvent) {
    header('Location: event.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $image = '';

    // Validate inputs
    if (empty($title)) {
        $error = 'Event title is required.';
    } elseif (empty($description)) {
        $error = 'Event description is required.';
    } elseif (empty($location)) {
        $error = 'Event location is required.';
    } elseif (empty($event_date)) {
        $error = 'Event date and time is required.';
    } else {
        // Validate event date is in the future
        $eventDateTime = new DateTime($event_date);
        $now = new DateTime();
        if ($eventDateTime <= $now) {
            $error = 'Event date must be in the future.';
        } else {
            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                $imageFile = $_FILES['image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($imageFile['type'], $allowedTypes)) {
                    $error = 'Invalid image format. Only JPEG, PNG, GIF, and WebP are allowed.';
                } elseif ($imageFile['size'] > 2 * 1024 * 1024) {
                    $error = 'Image size must be less than 2MB.';
                } else {
                    // Generate unique filename
                    $uploadDir = '../images/events/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = uniqid('event_') . '.' . pathinfo($imageFile['name'], PATHINFO_EXTENSION);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($imageFile['tmp_name'], $filePath)) {
                        $image = 'events/' . $fileName;
                    } else {
                        $error = 'Failed to upload image.';
                    }
                }
            }

            // If no error, insert event into database
            if (empty($error)) {
                $sql = "INSERT INTO events (title, description, location, event_date, image, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('sssssi', $title, $description, $location, $event_date, $image, $userId);
                    
                    if ($stmt->execute()) {
                        $success = 'Event created successfully! Redirecting...';
                        header('Refresh: 2; url=event.php');
                    } else {
                        $error = 'Error creating event. Please try again.';
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event</title>
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        .form-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 18px rgba(16,24,40,0.06);
            padding: 40px;
        }

        .form-card h1 {
            margin: 0 0 10px;
            font-size: 1.8rem;
            color: #0b1220;
        }

        .form-card p {
            color: #6b7280;
            margin: 0 0 30px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0b1220;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input[type="file"] {
            padding: 8px;
            border: 2px dashed #d1d5db;
            background: #f9fafb;
        }

        .form-group input[type="file"]:hover {
            border-color: #2563eb;
            background: #f0f5ff;
        }

        .file-help {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 6px;
        }

        .form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-submit {
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #1e40af;
        }

        .btn-cancel {
            padding: 12px 24px;
            background: #e5e7eb;
            color: #0b1220;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .required {
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .form-card {
                padding: 24px;
            }

            .form-actions {
                grid-template-columns: 1fr;
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
                <li><a href="dashboard.php" class="btn small">Dashboard</a></li>
            </ul>
        </nav>
    </header>

    <div class="form-container">
        <div class="form-card">
            <h1>Create New Event</h1>
            <p>Share your upcoming event with our community</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Event Title <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        placeholder="e.g., Web Development Workshop"
                        value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea 
                        id="description" 
                        name="description" 
                        placeholder="Describe your event in detail..."
                        required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="location">Location <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="location" 
                        name="location" 
                        placeholder="e.g., Online / Building A, Room 101"
                        value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="event_date">Date & Time <span class="required">*</span></label>
                    <input 
                        type="datetime-local" 
                        id="event_date" 
                        name="event_date" 
                        value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="image">Event Image (Optional)</label>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/*">
                    <p class="file-help">Accepted formats: JPEG, PNG, GIF, WebP. Max size: 2MB</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Event</button>
                    <a href="event.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
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
                    <li><a href="event.php">Events</a></li>
                    <li><a href="FAQ.html">FAQ</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
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
