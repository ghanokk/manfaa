<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate inputs
if (empty($eventId) || $eventId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

if (empty($fullName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Full name is required']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

// Check if event exists
$eventSql = "SELECT id FROM events WHERE id = ?";
$eventStmt = $conn->prepare($eventSql);
$eventStmt->bind_param('i', $eventId);
$eventStmt->execute();
$eventResult = $eventStmt->get_result();

if ($eventResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    $eventStmt->close();
    exit;
}
$eventStmt->close();

// Check if already registered
$checkSql = "SELECT id FROM event_registrations WHERE event_id = ? AND email = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('is', $eventId, $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You are already registered for this event']);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// Insert registration
$sql = "INSERT INTO event_registrations (event_id, full_name, email, phone, message, registered_at) 
        VALUES (?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('issss', $eventId, $fullName, $email, $phone, $message);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Registration successful! We have received your information.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error registering for event. Please try again.']);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}

$conn->close();
?>
