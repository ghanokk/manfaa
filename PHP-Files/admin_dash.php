<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

// Check if user is admin
$isAdmin = false;
if (is_array($userRole)) {
    $isAdmin = in_array('admin', $userRole);
} else {
    $isAdmin = ($userRole === 'admin');
}

if (!$isAdmin) {
    die("Access denied. Admin only.");
}

// Handle delete course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course_id'])) {
    $courseId = intval($_POST['delete_course_id']);
    $deleteSql = "DELETE FROM courses WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('i', $courseId);
    $deleteStmt->execute();
    $deleteStmt->close();
    $_SESSION['success_message'] = 'Course deleted successfully.';
    header('Location: admin_dash.php');
    exit;
}

// Handle delete event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $eventId = intval($_POST['delete_event_id']);
    $deleteSql = "DELETE FROM events WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('i', $eventId);
    $deleteStmt->execute();
    $deleteStmt->close();
    $_SESSION['success_message'] = 'Event deleted successfully.';
    header('Location: admin_dash.php');
    exit;
}

// Handle ban/unban user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user_id'])) {
    $banUserId = intval($_POST['ban_user_id']);
    $newStatus = $_POST['ban_status'] ?? 'banned';
    
    if ($banUserId !== $userId) { // Can't ban yourself
        $updateSql = "UPDATE users SET status = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('si', $newStatus, $banUserId);
        $updateStmt->execute();
        $updateStmt->close();
        $_SESSION['success_message'] = 'User status updated successfully.';
    }
    header('Location: admin_dash.php');
    exit;
}

// Handle change user role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role_user_id'])) {
    $changeUserId = intval($_POST['change_role_user_id']);
    $newRole = $_POST['new_role'] ?? 'student';
    
    if ($changeUserId !== $userId) { // Can't change your own role
        $updateSql = "UPDATE users SET role = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('si', $newRole, $changeUserId);
        $updateStmt->execute();
        $updateStmt->close();
        $_SESSION['success_message'] = 'User role updated successfully.';
    }
    header('Location: admin_dash.php');
    exit;
}

// Handle add course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $title = $_POST['course_title'] ?? '';
    $description = $_POST['course_description'] ?? '';
    $price = floatval($_POST['course_price'] ?? 0);
    
    if (!empty($title)) {
        $insertSql = "INSERT INTO courses (title, description, price, created_by, created_at) VALUES (?, ?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('ssdi', $title, $description, $price, $userId);
        $insertStmt->execute();
        $insertStmt->close();
        $_SESSION['success_message'] = 'Course added successfully.';
    }
    header('Location: admin_dash.php');
    exit;
}

// Handle add event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title = $_POST['event_title'] ?? '';
    $description = $_POST['event_description'] ?? '';
    $location = $_POST['event_location'] ?? '';
    $eventDate = $_POST['event_date'] ?? '';
    
    if (!empty($title) && !empty($eventDate)) {
        $insertSql = "INSERT INTO events (title, description, location, event_date, user_id) VALUES (?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('ssssi', $title, $description, $location, $eventDate, $userId);
        $insertStmt->execute();
        $insertStmt->close();
        $_SESSION['success_message'] = 'Event added successfully.';
    }
    header('Location: admin_dash.php');
    exit;
}

// Fetch all courses
$coursesSql = "SELECT c.id, c.title, c.description, c.price, c.created_by, u.name FROM courses c LEFT JOIN users u ON c.created_by = u.id";
$coursesStmt = $conn->prepare($coursesSql);
$coursesStmt->execute();
$coursesResult = $coursesStmt->get_result();
$courses = [];
while ($course = $coursesResult->fetch_assoc()) {
    $courses[] = $course;
}
$coursesStmt->close();

// Fetch all events
$eventsSql = "SELECT e.id, e.title, e.description, e.location, e.event_date, e.user_id, u.name FROM events e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.event_date DESC";
$eventsStmt = $conn->prepare($eventsSql);
$eventsStmt->execute();
$eventsResult = $eventsStmt->get_result();
$events = [];
while ($event = $eventsResult->fetch_assoc()) {
    $events[] = $event;
}
$eventsStmt->close();

// Fetch all users
$usersSql = "SELECT id, name, email, status FROM users ORDER BY id DESC";
$usersStmt = $conn->prepare($usersSql);
$usersStmt->execute();
$usersResult = $usersStmt->get_result();
$users = [];
while ($user = $usersResult->fetch_assoc()) {
    $users[] = $user;
}
$usersStmt->close();

// Get stats
$totalCourses = count($courses);
$totalEvents = count($events);
$totalUsers = count($users);
$bannedUsers = count(array_filter($users, function($u) { return $u['status'] === 'banned'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 40px;
        }

        .admin-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .admin-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0b1220;
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .action-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: #f3f4f6;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #0b1220;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            background: #dc2626;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #0b1220;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
        }

        .btn-submit {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: #1e40af;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-danger:hover {
            background: #991b1b;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background: #f9fafb;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #0b1220;
            border-bottom: 2px solid #e5e7eb;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #0b1220;
        }

        table tr:hover {
            background: #f9fafb;
        }

        .status-active {
            color: #22c55e;
            font-weight: 600;
        }

        .status-banned {
            color: #dc2626;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        @media (max-width: 768px) {
            .admin-stats {
                grid-template-columns: 1fr 1fr;
            }

            .table-container {
                font-size: 0.9rem;
            }

            table th, table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="navbar">
            <div class="brand" style="font-size: 1.5rem; font-weight: 700; color: white;">
                🛡️ Admin Panel
            </div>
            <div class="nav-right">
                <a class="link" href="dashboard.php">Dashboard</a>
                <a class="link" href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <div class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h1>👨‍💼 Administrator Dashboard</h1>
            <p>Manage courses, events, and users</p>
            <div class="admin-stats">
                <div class="stat-card">
                    <div class="stat-label">📚 Total Courses</div>
                    <div class="stat-number"><?php echo $totalCourses; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">🎓 Total Events</div>
                    <div class="stat-number"><?php echo $totalEvents; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">👥 Total Users</div>
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">🚫 Banned Users</div>
                    <div class="stat-number"><?php echo $bannedUsers; ?></div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success">✓ <?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error">✗ <?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Courses Section -->
        <div class="admin-section">
            <h2 class="section-title">📚 Manage Courses</h2>
            <div class="action-tabs">
                <button class="tab-btn active" onclick="switchTab('courses-list')">View All Courses</button>
                <button class="tab-btn" onclick="switchTab('courses-add')">Add New Course</button>
            </div>

            <!-- View Courses -->
            <div id="courses-list" class="tab-content active">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Price</th>
                                <th>Created By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo $course['id']; ?></td>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td>$<?php echo number_format($course['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($course['name'] ?? 'System'); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this course?');">
                                            <input type="hidden" name="delete_course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" class="btn-danger">🗑️ Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Course -->
            <div id="courses-add" class="tab-content">
                <form method="POST">
                    <div class="form-group">
                        <label>Course Title</label>
                        <input type="text" name="course_title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="course_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" name="course_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_course" value="1" class="btn-submit">➕ Add Course</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events Section -->
        <div class="admin-section">
            <h2 class="section-title">🎓 Manage Events</h2>
            <div class="action-tabs">
                <button class="tab-btn active" onclick="switchTab('events-list')">View All Events</button>
                <button class="tab-btn" onclick="switchTab('events-add')">Add New Event</button>
            </div>

            <!-- View Events -->
            <div id="events-list" class="tab-content active">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Created By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?php echo $event['id']; ?></td>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['name'] ?? 'System'); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event?');">
                                            <input type="hidden" name="delete_event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="btn-danger">🗑️ Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Event -->
            <div id="events-add" class="tab-content">
                <form method="POST">
                    <div class="form-group">
                        <label>Event Title</label>
                        <input type="text" name="event_title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="event_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="event_location" required>
                    </div>
                    <div class="form-group">
                        <label>Date & Time</label>
                        <input type="datetime-local" name="event_date" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_event" value="1" class="btn-submit">➕ Add Event</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Section -->
        <div class="admin-section">
            <h2 class="section-title">👥 Manage Users</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="change_role_user_id" value="<?php echo $user['id']; ?>">
                                        <select name="new_role" onchange="this.form.submit()" style="padding: 5px; border: 1px solid #e5e7eb; border-radius: 4px;">
                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="instructor" <?php echo $user['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="<?php echo $user['status'] === 'banned' ? 'status-banned' : 'status-active'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['id'] !== $userId): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="ban_user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="ban_status" value="<?php echo $user['status'] === 'banned' ? 'active' : 'banned'; ?>">
                                            <button type="submit" class="<?php echo $user['status'] === 'banned' ? 'btn-secondary' : 'btn-danger'; ?>">
                                                <?php echo $user['status'] === 'banned' ? '✓ Unban' : '🚫 Ban'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                    <li><a href="hpage.php">Home</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="event.php">Events</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li>Email: support@Ifada.com</li>
                    <li>Phone: +1 234 567 890</li>
                    <li>Address: 123 Learning Street</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            © 2025 Ifada — Admin Dashboard
        </div>
    </footer>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });

            // Show the selected tab
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
                event.target.classList.add('active');
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
