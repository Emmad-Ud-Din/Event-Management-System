<?php
/**
  *Get All Events 
 * Returns all events in the database 
 *  is accessible to guests 
 */

require_once 'config.php';
require_once 'db.php';

// content type : JSON
header('Content-Type: application/json');

try {
    // Fetch all events with organizer information
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
            e.updated_at,
            u.full_name as organizer_name,
            u.email as organizer_email
        FROM events e
        INNER JOIN users u ON e.organizer_id = u.id
        ORDER BY e.event_date ASC
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
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

