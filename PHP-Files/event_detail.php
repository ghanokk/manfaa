<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && isset($_POST['event_id'])) {
    $regEventId = intval($_POST['event_id']);
    
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
        $eventStmt->bind_param('i', $regEventId);
        $eventStmt->execute();
        $eventResult = $eventStmt->get_result();
        $eventInfo = $eventResult->fetch_assoc();
        $eventStmt->close();
        
        if ($eventInfo) {
            // Check if already registered
            $checkSql = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('ii', $regEventId, $userId);
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
                $insertStmt->bind_param('iisss', $regEventId, $userId, $userInfo['name'], $userInfo['email'], $ticketNumber);
                
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

// Get event ID from URL
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($eventId <= 0) {
    die("Invalid event ID.");
}

// Fetch event details with publisher info
$sql = "SELECT e.id, e.title, e.description, e.location, e.event_date, e.image, e.user_id, 
               u.name, u.email 
        FROM events e 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE e.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Event not found.");
}

$event = $result->fetch_assoc();
$stmt->close();

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
    return $date->format('F d, Y \a\t g:i A');
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

$statusInfo = getEventStatus($event['event_date']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Event Detail</title>
    <link rel="stylesheet" href="../styles/HomePage.css">
    <style>
        .hero-section {
            background: linear-gradient(180deg, rgba(37,99,235,0.08), transparent 70%);
            padding: 40px 16px;
        }

        .hero-content {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .hero-image {
            width: 100%;
            height: 350px;
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            background: white;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .hero-text h1 {
            margin: 0 0 20px;
            font-size: 2rem;
            color: #0b1220;
        }

        .hero-meta {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 24px;
        }

        .meta-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .meta-item strong {
            color: #0b1220;
            min-width: 80px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-register {
            flex: 1;
            padding: 12px 24px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-register:hover {
            background: #20c997;
        }

        .btn-back {
            flex: 1;
            padding: 12px 24px;
            background: white;
            color: #2563eb;
            border: 2px solid #2563eb;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #f0f5ff;
        }

        .details-section {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .main-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 18px rgba(16,24,40,0.06);
        }

        .main-content h2 {
            margin: 0 0 16px;
            font-size: 1.5rem;
            color: #0b1220;
        }

        .main-content p {
            margin: 0 0 20px;
            color: #6b7280;
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 18px rgba(16,24,40,0.06);
        }

        .info-card h4 {
            margin: 0 0 16px;
            font-size: 1.1rem;
            color: #0b1220;
        }

        .info-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .info-value {
            color: #0b1220;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .publisher-card {
            text-align: center;
        }

        .publisher-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2563eb, #6fb1ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto 16px;
        }

        .publisher-name {
            font-size: 1.1rem;
            color: #0b1220;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .publisher-title {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .publisher-email {
            font-size: 0.85rem;
            color: #2563eb;
            word-break: break-all;
        }

        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .hero-text h1 {
                font-size: 1.5rem;
            }
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

    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-image">
                <?php if (!empty($event['image'])): ?>
                    <img src="<?php echo htmlspecialchars(getImagePath($event['image'])); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                <?php else: ?>
                    <span style="font-size: 100px;">📅</span>
                <?php endif; ?>
                <div class="status-badge" style="color: <?php echo $statusInfo['color']; ?>">
                    <?php echo $statusInfo['badge']; ?>
                </div>
            </div>

            <div class="hero-text">
                <h1><?php echo htmlspecialchars($event['title']); ?></h1>
                
                <div class="hero-meta">
                    <div class="meta-item">
                        <strong>📍 Location:</strong>
                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong>🕐 Date & Time:</strong>
                        <span><?php echo formatEventDate($event['event_date']); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong>📊 Status:</strong>
                        <span><?php echo $statusInfo['status']; ?></span>
                    </div>
                </div>

                <div class="action-buttons">
                    <?php if ($statusInfo['status'] === 'Coming Soon'): ?>
                        <?php if ($isLoggedIn): ?>
                            <form method="POST" action="" style="flex: 1;">
                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" class="btn-register" style="width: 100%; cursor: pointer;">Register Now</button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="btn-register" style="display: flex; align-items: center; justify-content: center; text-decoration: none;">Login to Register</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="event.php" class="btn-back">← Back to Events</a>
                </div>
            </div>
        </div>
    </section>

    <div class="details-section">
        <div class="details-grid">
            <article class="main-content">
                <h2>Event Details</h2>
                <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>

                <h2 style="margin-top: 30px;">What to Expect</h2>
                <p>Join us for this exciting event where you'll have the opportunity to learn, network, and engage with industry experts and fellow enthusiasts. This is a great opportunity to expand your knowledge and connect with our community.</p>
            </article>

            <aside class="sidebar">
                <div class="info-card">
                    <h4>📋 Event Information</h4>
                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($event['location']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date & Time</div>
                        <div class="info-value"><?php echo formatEventDate($event['event_date']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value" style="color: <?php echo $statusInfo['color']; ?>; font-weight: 600;">
                            <?php echo $statusInfo['status']; ?>
                        </div>
                    </div>
                </div>

                <div class="info-card publisher-card">
                    <h4>👤 Event Organizer</h4>
                    <div class="publisher-avatar">
                        <?php 
                        if (!empty($event['full_name'])) {
                            echo strtoupper(substr($event['full_name'], 0, 1));
                        } else {
                            echo '?';
                        }
                        ?>
                    </div>
                    <div class="publisher-name">
                        <?php echo htmlspecialchars($event['full_name'] ?? 'Unknown'); ?>
                    </div>
                    <div class="publisher-title">Professional Educator</div>
                    <?php if (!empty($event['email'])): ?>
                        <div class="publisher-email">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($event['email']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
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
$conn->close();
?>
