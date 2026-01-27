<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && isset($_POST['event_id'])) {
    $eventId = intval($_POST['event_id']);
    
    // Get user info
    $userSql = "SELECT name, email FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userSql);
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userInfo = $userResult->fetch_assoc();
    $userStmt->close();
    
    if ($userInfo) {
        // Get event details
        $eventSql = "SELECT id, title, event_date FROM events WHERE id = ?";
        $eventStmt = $conn->prepare($eventSql);
        $eventStmt->bind_param('i', $eventId);
        $eventStmt->execute();
        $eventResult = $eventStmt->get_result();
        $eventInfo = $eventResult->fetch_assoc();
        $eventStmt->close();
        
        if ($eventInfo) {
            // Check if already registered
            $checkSql = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('ii', $eventId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $_SESSION['error_message'] = 'You are already registered for this event.';
                $checkStmt->close();
            } else {
                $checkStmt->close();
                
                // Generate ticket number
                $ticketNumber = 'TICKET-' . strtoupper(substr(md5(uniqid()), 0, 8));
                
                // Insert registration into database
                $insertSql = "INSERT INTO event_registrations (event_id, user_id, full_name, email, ticket_number, registered_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param('iisss', $eventId, $userId, $userInfo['name'], $userInfo['email'], $ticketNumber);
                
                if ($insertStmt->execute()) {
                    // Store in session for immediate display
                    $_SESSION['event_ticket'] = [
                        'ticket_number' => $ticketNumber,
                        'event_title' => $eventInfo['title'],
                        'event_date' => $eventInfo['event_date'],
                        'full_name' => $userInfo['name'],
                        'email' => $userInfo['email'],
                        'registered_at' => date('Y-m-d H:i:s')
                    ];
                    $_SESSION['success_message'] = 'Successfully registered for the event!';
                    $insertStmt->close();
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to register. Please try again.';
                    $insertStmt->close();
                }
            }
        }
    }
}

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

// Fetch all events with creator info
$sql = "SELECT e.id, e.title, e.description, e.event_date, e.location, e.image, e.user_id, u.name 
        FROM events e 
        LEFT JOIN users u ON e.user_id = u.id 
        ORDER BY e.event_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Helper function to get event status
function getEventStatus($eventDate) {
    $now = new DateTime();
    $event = new DateTime($eventDate);
    
    if ($event > $now) {
        $diff = $now->diff($event);
        if ($diff->days > 7) {
            return ['status' => 'Coming Soon', 'badge' => '⏳ Coming Soon', 'color' => '#6b7280'];
        } elseif ($diff->days > 0) {
            return ['status' => 'Starting Soon', 'badge' => '⚡ Starting Soon', 'color' => '#f59e0b'];
        } else {
            return ['status' => 'Today', 'badge' => '🔴 Today', 'color' => '#ef4444'];
        }
    } elseif ($event->format('Y-m-d') === $now->format('Y-m-d')) {
        return ['status' => 'Today', 'badge' => '🔴 Today', 'color' => '#ef4444'];
    } else {
        return ['status' => 'Completed', 'badge' => '✓ Completed', 'color' => '#28a745'];
    }
}

// Helper function to format date
function formatEventDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M d, Y \a\t g:i A');
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
    <title>Events</title>
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        .events-hero {
            background: linear-gradient(180deg, rgba(37,99,235,0.06), transparent 60%);
            padding: 40px 16px 20px;
            text-align: center;
        }

        .events-hero h1 {
            font-size: 2rem;
            color: #0b1220;
            margin: 0 0 10px;
        }

        .events-hero p {
            color: #6b7280;
            margin: 0;
        }

        .events-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .events-header .event-count {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .events-header .btn-create {
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .events-header .btn-create:hover {
            background: #1e40af;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 18px rgba(16,24,40,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(16, 24, 40, 0.35);
            background-color: #19293536;
        }

        .event-card-image {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .event-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            background: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .event-card-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .event-card-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #0b1220;
            margin: 0 0 10px;
            line-height: 1.4;
        }

        .event-card-meta {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-publisher {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 8px 0 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .event-description {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 16px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-register {
            padding: 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
            text-decoration: none;
        }

        .btn-register:hover {
            background: #20c997;
        }

        .btn-details {
            padding: 10px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s;
        }

        .btn-details:hover {
            background: #1e40af;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease-out;
            overflow: hidden;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            color: white;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: transform 0.2s;
        }

        .modal-close:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0b1220;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .required {
            color: #dc2626;
        }

        .modal-footer {
            padding: 20px 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .btn-modal-submit {
            padding: 10px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-modal-submit:hover {
            background: #20c997;
        }

        .btn-modal-cancel {
            padding: 10px 16px;
            background: #e5e7eb;
            color: #0b1220;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-modal-cancel:hover {
            background: #d1d5db;
        }

        .alert-modal {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .alert-error-modal {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-success-modal {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        @media (max-width: 768px) {
            .events-grid {
                grid-template-columns: 1fr;
            }

            .events-header {
                flex-direction: column;
                align-items: stretch;
            }

            .events-header .btn-create {
                width: 100%;
            }

            .event-card-actions {
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
                <li><a href="hpage.php">Home</a></li>
                <li><a href="courses.php">Courses</a></li>
                <li><a href="event.php">Events</a></li>
                <li><a href="FAQ.html">FAQ</a></li>
                <?php if ($isLoggedIn): ?>
                    <li><a href="dashboard.php" class="btn small">Dashboard</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn small">Sign in</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <section class="events-hero">
        <h1>Upcoming Events</h1>
        <p><?php echo $result->num_rows; ?> events available</p>
    </section>

    <div class="events-container">
        <div class="events-header">
            <span class="event-count">Showing <?php echo $result->num_rows; ?> events</span>
            <?php if ($canCreateEvent): ?>
                <a href="create_event.php" class="btn-create">+ Create Event</a>
            <?php endif; ?>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="events-grid">
                <?php while ($event = $result->fetch_assoc()): 
                    $statusInfo = getEventStatus($event['event_date']);
                ?>
                    <div class="event-card">
                        <div class="event-card-image">
                            <?php if (!empty($event['image'])): ?>
                                <img src="<?php echo htmlspecialchars(getImagePath($event['image'])); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                            <?php else: ?>
                                <span style="font-size: 70px;">📅</span>
                            <?php endif; ?>
                            <div class="event-status-badge" style="color: <?php echo $statusInfo['color']; ?>">
                                <?php echo $statusInfo['badge']; ?>
                            </div>
                        </div>
                        <div class="event-card-content">
                            <h3 class="event-card-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-card-meta">
                                📍 <?php echo htmlspecialchars($event['location']); ?>
                            </div>
                            <div class="event-card-meta">
                                🕐 <?php echo formatEventDate($event['event_date']); ?>
                            </div>
                            <?php if (!empty($event['full_name'])): ?>
                                <div class="event-publisher">
                                    👤 <?php echo htmlspecialchars($event['full_name']); ?>
                                </div>
                            <?php endif; ?>
                            <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                            <div class="event-card-actions">
                                <?php if ($statusInfo['status'] === 'Coming Soon'): ?>
                                    <?php if ($isLoggedIn): ?>
                                        <form method="POST" action="" style="flex: 1;">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="btn-register" style="width: 100%; cursor: pointer;">Register</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="login.php" class="btn-register" style="display: flex; align-items: center; justify-content: center;">Login</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn-details">More Details</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📅</div>
                <p>No events available at the moment. Check back soon!</p>
            </div>
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
                    <li><a href="hpage.php">Home</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="event.php">Events</a></li>
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

    <!-- Registration Modal -->
    <div id="registrationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Register for Event</h2>
                <button class="modal-close" onclick="closeRegisterModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modalMessage"></div>
                <form id="registrationForm" method="POST" action="register_event.php">
                    <input type="hidden" id="eventId" name="event_id" value="">
                    
                    <div class="form-group">
                        <label for="fullName">Full Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="fullName" 
                            name="full_name" 
                            placeholder="Enter your full name"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number (Optional)</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="Enter your phone number">
                    </div>

                    <div class="form-group">
                        <label for="message">Message (Optional)</label>
                        <textarea 
                            id="message" 
                            name="message" 
                            placeholder="Any questions or comments?"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="registrationForm" class="btn-modal-submit">Confirm Registration</button>
                <button type="button" onclick="closeRegisterModal()" class="btn-modal-cancel">Cancel</button>
            </div>
        </div>
    </div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
