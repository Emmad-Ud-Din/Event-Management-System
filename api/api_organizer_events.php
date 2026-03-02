<?php
/**
 *Get Events by Organizer 
 * Returns all events created by a specific organizer as JSON format.
 * accessible to guests 
 */

require_once 'config.php';
require_once 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get organizer_id from query parameter
    $organizer_id = null;
    if (isset($_GET['organizer_id'])) {
        $organizer_id = intval($_GET['organizer_id']);
    }

    // Validate organizer_id
    if (!$organizer_id || $organizer_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing organizer_id parameter. Please provide a valid organizer ID.'
        ], JSON_PRETTY_PRINT);
        exit();
    }

    // Verify organizer exists
    $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND role = 'organizer'");
    $stmt->execute([$organizer_id]);
    $organizer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$organizer) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Organizer not found.'
        ], JSON_PRETTY_PRINT);
        exit();
    }

    // Fetch all events by this organizer
    $stmt = $db->prepare("
        SELECT 
            e.id,
            e.organizer_id,
            e.title,
            e.description,
            e.artist,
            e.event_date,
            e.location,
            e.image_path,
            e.status,
            e.created_at,
            e.updated_at
        FROM events e
        WHERE e.organizer_id = ?
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$organizer_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'organizer' => [
            'id' => $organizer['id'],
            'name' => $organizer['full_name'],
            'email' => $organizer['email']
        ],
        'count' => count($events),
        'events' => $events
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

