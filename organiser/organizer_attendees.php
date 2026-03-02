<?php
require_once 'config.php';
requireOrganizer();

$organizer_id = $_SESSION['user_id'];

if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
} else {
    $event_id = 0;
}

$event = null;
if ($event_id > 0) {
    $stmt = $db->prepare("SELECT title, event_date, location FROM events WHERE id=? AND organizer_id=?");
    $stmt->execute([$event_id, $organizer_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}

$attendees = null;
if ($event) {
    $stmt = $db->prepare("
        SELECT u.full_name, u.email, er.registered_at
        FROM event_registrations er
        JOIN users u ON er.user_id = u.id
        WHERE er.event_id=?
        ORDER BY er.registered_at DESC
    ");
    $stmt->execute([$event_id]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendees - Concert & Event Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <a href="organizer_dashboard.php" class="btn btn-outline-secondary mb-3">Back to Dashboard</a>

        <?php if (!$event): ?>
            <div class="alert alert-danger">Event not found or you do not have access.</div>
        <?php else: ?>
            <h3>Attendees for "<?php echo htmlspecialchars($event['title']); ?>"</h3>
            <p class="text-muted">
                <?php echo date('Y-m-d H:i', strtotime($event['event_date'])); ?> ·
                <?php echo htmlspecialchars($event['location']); ?>
            </p>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendees && count($attendees) > 0): ?>
                            <?php foreach ($attendees as $attendee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attendee['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attendee['email']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($attendee['registered_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No attendees yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
