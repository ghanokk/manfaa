<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Handle delete registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_registration_id'])) {
    $regId = intval($_POST['delete_registration_id']);
    
    // Verify the registration belongs to the user
    $checkSql = "SELECT id FROM event_registrations WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('ii', $regId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Delete the registration
        $deleteSql = "DELETE FROM event_registrations WHERE id = ? AND user_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param('ii', $regId, $userId);
        
        if ($deleteStmt->execute()) {
            $_SESSION['success_message'] = 'Registration removed successfully.';
            $deleteStmt->close();
        } else {
            $_SESSION['error_message'] = 'Failed to remove registration.';
            $deleteStmt->close();
        }
    }
    $checkStmt->close();
    header('Location: dashboard.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? '';

// Get cart count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Fetch user information from database
$userSql = "SELECT id, name, email FROM users WHERE id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// Check if user is instructor or admin
$isInstructor = false;
if (is_array($userRole)) {
    $isInstructor = in_array('instructor', $userRole) || in_array('admin', $userRole);
} elseif (!empty($userRole)) {
    $isInstructor = $userRole === 'instructor' || $userRole === 'admin';
}

// Fetch enrolled courses count
$enrolledCountSql = "SELECT COUNT(*) as count FROM enrollments WHERE user_id = ?";
$enrolledStmt = $conn->prepare($enrolledCountSql);
$enrolledStmt->bind_param('i', $userId);
$enrolledStmt->execute();
$enrolledResult = $enrolledStmt->get_result();
$enrolledData = $enrolledResult->fetch_assoc();
$enrolledCount = $enrolledData['count'] ?? 0;
$enrolledStmt->close();

// Fetch created courses count (if instructor/admin)
$createdCount = 0;
if ($isInstructor) {
    $createdCountSql = "SELECT COUNT(*) as count FROM courses WHERE created_by = ?";
    $createdStmt = $conn->prepare($createdCountSql);
    $createdStmt->bind_param('i', $userId);
    $createdStmt->execute();
    $createdResult = $createdStmt->get_result();
    $createdData = $createdResult->fetch_assoc();
    $createdCount = $createdData['count'] ?? 0;
    $createdStmt->close();
}

// Fetch total users count (for admin only)
$totalUsers = 0;
if ($isInstructor && in_array('admin', (array)$userRole)) {
    $usersCountSql = "SELECT COUNT(*) as count FROM users";
    $usersStmt = $conn->prepare($usersCountSql);
    $usersStmt->execute();
    $usersResult = $usersStmt->get_result();
    $usersData = $usersResult->fetch_assoc();
    $totalUsers = $usersData['count'] ?? 0;
    $usersStmt->close();
}

// Fetch recent courses (first 3)
$recentCoursesSql = "SELECT id, title, price FROM courses LIMIT 3";
$recentStmt = $conn->prepare($recentCoursesSql);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
$recentCourses = [];
while ($course = $recentResult->fetch_assoc()) {
    $recentCourses[] = $course;
}
$recentStmt->close();

// Fetch enrolled courses (courses user has paid for)
$enrolledCoursesSql = "SELECT DISTINCT c.id, c.title, c.price, c.description
                       FROM courses c
                       INNER JOIN sessions s ON c.id = s.course_id
                       INNER JOIN enrollments e ON s.id = e.session_id
                       INNER JOIN payments p ON e.id = p.enrollment_id
                       WHERE e.user_id = ? AND p.status = 'paid'
                       ORDER BY e.created_at DESC";
$enrolledStmt = $conn->prepare($enrolledCoursesSql);
$enrolledStmt->bind_param('i', $userId);
$enrolledStmt->execute();
$enrolledResult = $enrolledStmt->get_result();
$enrolledCourses = [];
while ($course = $enrolledResult->fetch_assoc()) {
    $enrolledCourses[] = $course;
}
$enrolledStmt->close();

// Fetch created courses (if instructor/admin)
$createdCourses = [];
if ($isInstructor) {
    $createdCoursesSql = "SELECT id, title, price, description FROM courses WHERE created_by = ? ORDER BY id DESC";
    $createdStmt = $conn->prepare($createdCoursesSql);
    $createdStmt->bind_param('i', $userId);
    $createdStmt->execute();
    $createdResult = $createdStmt->get_result();
    while ($course = $createdResult->fetch_assoc()) {
        $createdCourses[] = $course;
    }
    $createdStmt->close();
}

// Get display role
$displayRole = 'Student';
if (is_array($userRole) && !empty($userRole)) {
    $displayRole = implode(', ', array_map('ucfirst', $userRole));
} elseif (!empty($userRole)) {
    $displayRole = ucfirst($userRole);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../styles/Courses.css">
    <link rel="stylesheet" href="../styles/HomePage.css">
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
            --dark-text: #0b1220;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            color: white;
            padding: 40px 20px;
            margin-bottom: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.25);
        }

        .dashboard-header h2 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .dashboard-header p {
            margin: 5px 0;
            opacity: 0.95;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stats-card:nth-child(1) { border-top: 5px solid var(--primary-blue); }
        .stats-card:nth-child(2) { border-top: 5px solid var(--primary-purple); }
        .stats-card:nth-child(3) { border-top: 5px solid var(--primary-pink); }
        .stats-card:nth-child(4) { border-top: 5px solid var(--primary-orange); }

        .stats-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 10px 0;
        }

        .stats-label {
            color: #6b7280;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-text);
            margin: 40px 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .user-info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 2px solid #f0f4ff;
        }

        .user-info-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .user-info-details h3 {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            color: var(--dark-text);
        }

        .user-info-details p {
            margin: 5px 0;
            color: #6b7280;
        }

        .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan));
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .action-btn {
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan));
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-block;
            font-size: 1rem;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.25);
        }

        .action-btn:nth-child(2) {
            background: linear-gradient(135deg, var(--primary-purple), var(--primary-pink));
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.25);
        }

        .action-btn:nth-child(3) {
            background: linear-gradient(135deg, var(--primary-orange), var(--primary-amber));
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.25);
        }

        .action-btn:nth-child(4) {
            background: linear-gradient(135deg, var(--primary-pink), var(--primary-red));
            box-shadow: 0 6px 20px rgba(236, 72, 153, 0.25);
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(37, 99, 235, 0.35);
        }

        .action-btn.secondary {
            background: white;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
            box-shadow: none;
        }

        .action-btn.secondary:hover {
            background: #f0f4ff;
            transform: translateY(-4px);
        }

        .recent-courses {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .course-item {
            display: flex;
            background: linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%);
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            border-radius: 12px;
            border-left: 5px solid var(--primary-blue);
            margin-bottom: 14px;
            transition: all 0.3s ease;
        }

        .course-item:nth-child(even) {
            border-left-color: var(--primary-purple);
            background: linear-gradient(135deg, #faf5ff 0%, #ffffff 100%);
        }

        .course-item:nth-child(3n) {
            border-left-color: var(--primary-pink);
            background: linear-gradient(135deg, #fdf2f8 0%, #ffffff 100%);
        }

        .course-item:hover {
            transform: translateX(6px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .course-item:last-child {
            margin-bottom: 0;
        }

        .course-item-name {
            font-weight: 700;
            color: var(--dark-text);
            font-size: 1.05rem;
        }

        .course-item-price {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .course-item-action {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan));
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.95rem;
            margin-left: 10px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .course-item-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .user-info-header {
                flex-direction: column;
                text-align: center;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <h1>Dashboard</h1>
        <nav class="navbar">
            <a class="link" href="homePage.php" style="padding:8px 10px; font-size:0.95rem;border-radius: 15px; position: relative; background-color: #0864c5ff;color: white;margin-left: 30px;">Ifada</a>
            <form class="nav-search" role="search">
                <input type="search" placeholder="Search courses..." aria-label="Search courses">
            </form>
            <div class="nav-right">
                <?php if (in_array('admin', (array)$userRole)): ?>
                    <a class="link" href="admin_dash.php" style="padding:8px 10px; font-size:0.95rem; border-radius: 15px; background-color: #dc2626; color: white; margin-right: 10px;">
                        🛡️ Admin Panel
                    </a>
                <?php endif; ?>
                <a class="link" href="logout.php">Logout</a>
                <a class="link" href="cart.php" style="padding:8px 10px; font-size:0.95rem;border-radius: 15px; position: relative; background-color: #0864c5ff;color: white;margin-left: 30px;">
                    🛒 Cart
                    <?php if ($cartCount > 0): ?>
                        <span style="position: absolute; top: -8px; right: -10px; background-color: #dc3545; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
    </header>

    <div class="dashboard-container">
        <!-- Welcome Header -->
        <div class="dashboard-header">
            <h2>Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>! 👋</h2>
            <p>Here's your learning dashboard</p>
        </div>

        <?php
        // Display event ticket if available
        if (isset($_SESSION['event_ticket'])) {
            $ticket = $_SESSION['event_ticket'];
            $eventDate = new DateTime($ticket['event_date']);
            ?>
            <!-- Compact Ticket Card -->
            <div onclick="openTicketModal()" style="background: linear-gradient(135deg, #2563eb, #6fb1ff); border-radius: 12px; padding: 16px; margin-bottom: 30px; color: white; box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(37, 99, 235, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 16px rgba(37, 99, 235, 0.3)';">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                        <div style="font-size: 32px;">🎫</div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem;">Event Registration Confirmed</h3>
                            <p style="margin: 3px 0 0 0; opacity: 0.9; font-size: 0.85rem;"><?php echo htmlspecialchars($ticket['event_title']); ?></p>
                        </div>
                    </div>
                    <div style="text-align: right; white-space: nowrap;">
                        <p style="margin: 0 0 4px 0; font-size: 0.75rem; opacity: 0.8;">Ticket #</p>
                        <p style="margin: 0; font-weight: 700; font-size: 0.95rem; font-family: 'Courier New', monospace;"><?php echo substr(htmlspecialchars($ticket['ticket_number']), -6); ?></p>
                    </div>
                </div>
                <p style="margin: 8px 0 0 0; font-size: 0.8rem; opacity: 0.85;">Click to view full details</p>
            </div>

            <!-- Ticket Modal Popup -->
            <div id="ticketModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5);">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideDown 0.3s ease-out;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #0b1220;">Event Ticket</h2>
                        <button onclick="closeTicketModal()" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>

                    <div style="background: linear-gradient(135deg, #2563eb, #6fb1ff); border-radius: 8px; padding: 20px; color: white; margin-bottom: 20px;">
                        <div style="text-align: center; margin-bottom: 15px;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🎫</div>
                            <h3 style="margin: 0 0 5px 0; font-size: 1.3rem;">Registration Confirmed</h3>
                            <p style="margin: 0; opacity: 0.9;">Your ticket has been issued</p>
                        </div>
                    </div>

                    <div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Ticket Number</p>
                                <p style="margin: 0; font-weight: 700; font-size: 1.1rem; color: #0b1220; font-family: 'Courier New', monospace;"><?php echo htmlspecialchars($ticket['ticket_number']); ?></p>
                            </div>
                            <div>
                                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Event Date</p>
                                <p style="margin: 0; font-weight: 600; font-size: 1rem; color: #0b1220;"><?php echo $eventDate->format('M d, Y'); ?></p>
                                <p style="margin: 3px 0 0 0; color: #6b7280; font-size: 0.85rem;"><?php echo $eventDate->format('g:i A'); ?></p>
                            </div>
                        </div>
                        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px;">
                            <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Event</p>
                            <p style="margin: 0 0 15px 0; font-weight: 600; font-size: 1rem; color: #0b1220;"><?php echo htmlspecialchars($ticket['event_title']); ?></p>
                            
                            <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Attendee</p>
                            <p style="margin: 0 0 4px 0; font-weight: 600; font-size: 1rem; color: #0b1220;"><?php echo htmlspecialchars($ticket['full_name']); ?></p>
                            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 0.9rem;"><?php echo htmlspecialchars($ticket['email']); ?></p>
                            
                            <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Registered On</p>
                            <p style="margin: 0; color: #0b1220; font-size: 0.9rem;"><?php echo date('M d, Y g:i A', strtotime($ticket['registered_at'])); ?></p>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button onclick="window.print()" style="flex: 1; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;">🖨️ Print Ticket</button>
                        <button onclick="closeTicketModal()" style="flex: 1; background: #f3f4f6; color: #0b1220; border: none; padding: 12px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;">Close</button>
                    </div>
                </div>
            </div>

            <style>
                @keyframes slideDown {
                    from {
                        transform: translate(-50%, -60px);
                        opacity: 0;
                    }
                    to {
                        transform: translate(-50%, -50%);
                        opacity: 1;
                    }
                }
            </style>

            <script>
                function openTicketModal() {
                    document.getElementById('ticketModal').style.display = 'flex';
                    document.getElementById('ticketModal').style.alignItems = 'center';
                    document.getElementById('ticketModal').style.justifyContent = 'center';
                }
                
                function closeTicketModal() {
                    document.getElementById('ticketModal').style.display = 'none';
                }
                
                // Close modal when clicking outside
                document.getElementById('ticketModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeTicketModal();
                    }
                });
            </script>
            <?php
            // Clear ticket after display
            unset($_SESSION['event_ticket']);
        }
        
        // Display success message if exists
        if (isset($_SESSION['success_message'])) {
            echo '<div style="background: #dcfce7; color: #166534; padding: 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #22c55e;">✓ ' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        
        // Display error message if exists
        if (isset($_SESSION['error_message'])) {
            echo '<div style="background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc2626;">✗ ' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <!-- Stats Cards -->
        <div class="dashboard-grid">
            <div class="stats-card">
                <div class="stats-icon">🎯</div>
                <div class="stats-number"><?php echo $enrolledCount; ?></div>
                <div class="stats-label">Courses Enrolled</div>
            </div>

            <?php if ($isInstructor): ?>
                <div class="stats-card">
                    <div class="stats-icon">✏️</div>
                    <div class="stats-number"><?php echo $createdCount; ?></div>
                    <div class="stats-label">Courses Created</div>
                </div>
            <?php endif; ?>

            <?php if (in_array('admin', (array)$userRole)): ?>
                <div class="stats-card">
                    <div class="stats-icon">👥</div>
                    <div class="stats-number"><?php echo $totalUsers; ?></div>
                    <div class="stats-label">Total Users</div>
                </div>
            <?php endif; ?>

            <div class="stats-card">
                <div class="stats-icon">📚</div>
                <div class="stats-number"><?php echo count($recentCourses); ?></div>
                <div class="stats-label">Available Courses</div>
            </div>
        </div>

        <!-- User Info Section -->
        <h2 class="section-title">Profile Information</h2>
        <div class="user-info-card">
            <div class="user-info-header">
                <div class="user-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                <div class="user-info-details">
                    <h3><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h3>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                    <p><strong>Member ID:</strong> #<?php echo $userId; ?></p>
                    <div>
                        <span class="role-badge"><?php echo $displayRole; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">Quick Actions</h2>
        <div class="quick-actions">
            <a href="courses.php" class="action-btn">📚 Browse Courses</a>
            <a href="event.php" class="action-btn">🎓 View Events</a>
            <?php if ($isInstructor): ?>
                <a href="create_course.php" class="action-btn">➕ Create Course</a>
            <?php endif; ?>
            <a href="cart.php" class="action-btn secondary">🛒 View Cart (<?php echo $cartCount; ?>)</a>
        </div>

        <!-- My Enrolled Courses -->
        <?php if (!empty($enrolledCourses)): ?>
            <h2 class="section-title">My Enrolled Courses</h2>
            <div class="recent-courses">
                <?php foreach ($enrolledCourses as $course): ?>
                    <div class="course-item">
                        <div>
                            <div class="course-item-name">✓ <?php echo htmlspecialchars($course['title']); ?></div>
                            <span class="course-item-price">$<?php echo number_format($course['price'], 2); ?></span>
                        </div>
                        <a href="cdetail.php?id=<?php echo $course['id']; ?>" class="course-item-action">View Course</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="recent-courses">
                <div class="empty-state">
                    <p style="font-size: 1.1rem; margin: 0 0 10px 0;">📚 No courses enrolled yet</p>
                    <p style="margin: 0;">Start learning by browsing our available courses!</p>
                    <a href="courses.php" class="action-btn" style="margin-top: 15px;">Browse Courses</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Created Courses (Instructor/Admin Only) -->
        <?php if ($isInstructor): ?>
            <h2 class="section-title">My Created Courses</h2>
            <?php if (!empty($createdCourses)): ?>
                <div class="recent-courses">
                    <?php foreach ($createdCourses as $course): ?>
                        <div class="course-item">
                            <div>
                                <div class="course-item-name">📝 <?php echo htmlspecialchars($course['title']); ?></div>
                                <span class="course-item-price">$<?php echo number_format($course['price'], 2); ?></span>
                            </div>
                            <a href="cdetail.php?id=<?php echo $course['id']; ?>" class="course-item-action">Edit</a>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; padding-top: 20px;">
                        <a href="create_course.php" class="action-btn">➕ Create New Course</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="recent-courses">
                    <div class="empty-state">
                        <p style="font-size: 1.1rem; margin: 0 0 10px 0;">📝 No courses created yet</p>
                        <p style="margin: 0;">Start creating your courses to share knowledge!</p>
                        <a href="create_course.php" class="action-btn" style="margin-top: 15px;">Create Your First Course</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Event Registrations History -->
        <?php
        $regSql = "SELECT er.id, er.ticket_number, er.registered_at, e.title, e.event_date 
                   FROM event_registrations er 
                   JOIN events e ON er.event_id = e.id 
                   WHERE er.user_id = ? 
                   ORDER BY er.registered_at DESC";
        $regStmt = $conn->prepare($regSql);
        $regStmt->bind_param('i', $userId);
        $regStmt->execute();
        $regResult = $regStmt->get_result();
        $registrations = [];
        while ($reg = $regResult->fetch_assoc()) {
            $registrations[] = $reg;
        }
        $regStmt->close();
        
        if (!empty($registrations)):
        ?>
            <h2 class="section-title">📅 My Event Registrations</h2>
            <div style="background: linear-gradient(135deg, #f0f4ff 0%, #faf5ff 100%); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <?php foreach ($registrations as $index => $reg): ?>
                    <?php 
                    $eventDateTime = new DateTime($reg['event_date']);
                    $now = new DateTime();
                    $isUpcoming = $eventDateTime > $now;
                    $statusColor = $isUpcoming ? '#2563eb' : '#6b7280';
                    $statusIcon = $isUpcoming ? '📅' : '✓';
                    $statusText = $isUpcoming ? 'Upcoming' : 'Completed';
                    ?>
                    <div style="padding: 20px; border-bottom: 1px solid #e5d7ff; background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan)); transition: background 0.2s;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <div style="font-size: 24px;">🎫</div>
                                    <h3 style="margin: 0; font-size: 1.1rem; color: #0b1220; font-weight: 700;"><?php echo htmlspecialchars($reg['title']); ?></h3>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 12px;">
                                    <div>
                                        <p style="margin: 0 0 4px 0; color: #2a2d31; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Ticket Number</p>
                                        <p style="margin: 0; font-weight: 700; font-size: 0.95rem; color: #0b1220; font-family: 'Courier New', monospace;"><?php echo htmlspecialchars($reg['ticket_number']); ?></p>
                                    </div>
                                    <div>
                                        <p style="margin: 0 0 4px 0; color: #2a2d31; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Event Date</p>
                                        <p style="margin: 0; font-weight: 600; color: #0b1220;"><?php echo $eventDateTime->format('M d, Y'); ?></p>
                                        <p style="margin: 2px 0 0 0; color: #2a2d31; font-size: 0.85rem;"><?php echo $eventDateTime->format('g:i A'); ?></p>
                                    </div>
                                    <div>
                                        <p style="margin: 0 0 4px 0; color: #2a2d31; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Registered</p>
                                        <p style="margin: 0; font-weight: 600; color: #0b1220;"><?php echo date('M d, Y', strtotime($reg['registered_at'])); ?></p>
                                        <p style="margin: 2px 0 0 0; color: #2a2d31; font-size: 0.85rem;"><?php echo date('g:i A', strtotime($reg['registered_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                                <span style="background: <?php echo $statusColor; ?>; color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; white-space: nowrap;"><?php echo $statusIcon; ?> <?php echo $statusText; ?></span>
                                <div style="display: flex; gap: 8px;">
                                    <button onclick="printRegistration('<?php echo htmlspecialchars($reg['ticket_number']); ?>', '<?php echo htmlspecialchars($reg['title']); ?>', '<?php echo $eventDateTime->format('M d, Y g:i A'); ?>', '<?php echo date('M d, Y g:i A', strtotime($reg['registered_at'])); ?>')" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease; white-space: nowrap; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);">🖨️ Print</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this registration?');">
                                        <input type="hidden" name="delete_registration_id" value="<?php echo $reg['id']; ?>">
                                        <button type="submit" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease; white-space: nowrap; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);">🗑️ Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
                function printRegistration(ticketNumber, eventTitle, eventDate, registeredDate) {
                    const printWindow = window.open('', '', 'width=600,height=800');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Event Registration Ticket</title>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    margin: 20px;
                                    color: #0b1220;
                                }
                                .ticket-container {
                                    max-width: 600px;
                                    margin: 0 auto;
                                    border: 3px dashed #667eea;
                                    padding: 40px;
                                    border-radius: 12px;
                                    text-align: center;
                                }
                                .ticket-header {
                                    background: linear-gradient(135deg, #667eea, #764ba2);
                                    color: white;
                                    padding: 30px;
                                    border-radius: 8px;
                                    margin-bottom: 30px;
                                }
                                .ticket-icon {
                                    font-size: 48px;
                                    margin-bottom: 10px;
                                }
                                .ticket-title {
                                    font-size: 24px;
                                    font-weight: bold;
                                    margin: 15px 0 5px 0;
                                }
                                .ticket-subtitle {
                                    font-size: 14px;
                                    opacity: 0.9;
                                }
                                .ticket-details {
                                    text-align: left;
                                    margin: 30px 0;
                                }
                                .detail-row {
                                    display: flex;
                                    justify-content: space-between;
                                    padding: 12px 0;
                                    border-bottom: 1px solid #e5e7eb;
                                }
                                .detail-label {
                                    font-weight: bold;
                                    color: #667eea;
                                    font-size: 12px;
                                    text-transform: uppercase;
                                }
                                .detail-value {
                                    font-weight: 600;
                                    color: #0b1220;
                                    font-size: 14px;
                                    font-family: 'Courier New', monospace;
                                }
                                .ticket-footer {
                                    margin-top: 30px;
                                    padding-top: 20px;
                                    border-top: 2px solid #667eea;
                                    font-size: 12px;
                                    color: #6b7280;
                                }
                                @media print {
                                    body { margin: 0; padding: 0; }
                                    .ticket-container { border: none; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="ticket-container">
                                <div class="ticket-header">
                                    <div class="ticket-icon">🎫</div>
                                    <div class="ticket-title">Event Registration</div>
                                    <div class="ticket-subtitle">Your Confirmation Ticket</div>
                                </div>
                                
                                <div class="ticket-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Ticket Number</span>
                                        <span class="detail-value">${ticketNumber}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Event Title</span>
                                        <span class="detail-value">${eventTitle}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Event Date</span>
                                        <span class="detail-value">${eventDate}</span>
                                    </div>
                                    <div class="detail-row" style="border-bottom: none;">
                                        <span class="detail-label">Registered On</span>
                                        <span class="detail-value">${registeredDate}</span>
                                    </div>
                                </div>
                                
                                <div class="ticket-footer">
                                    <p>Thank you for registering! Please keep this ticket for your records.</p>
                                    <p>© 2025 Ifada — Learn at your pace</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                }
            </script>
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
