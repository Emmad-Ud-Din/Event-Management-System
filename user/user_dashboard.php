<?php
require_once 'config.php';
requireUser();

$user_id = $_SESSION['user_id'];

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else {
        $action = '';
    }
    if (isset($_POST['event_id'])) {
        $event_id = intval($_POST['event_id']);
    } else {
        $event_id = 0;
    }

    if ($event_id <= 0) {
        $errors[] = 'Invalid event.';
    } elseif ($action === 'register_event') {
        $stmt = $db->prepare("SELECT id FROM events WHERE id=? AND status='approved'");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            $errors[] = 'This event is not available for registration.';
        } else {
            $stmt = $db->prepare("SELECT id FROM event_registrations WHERE event_id=? AND user_id=?");
            $stmt->execute([$event_id, $user_id]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                $errors[] = 'You are already registered for this event.';
            } else {
                $stmt = $db->prepare("INSERT INTO event_registrations (event_id, user_id) VALUES (?, ?)");
                if ($stmt->execute([$event_id, $user_id])) {
                    $message = 'You are registered for the event.';
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
        }
    } elseif ($action === 'cancel_registration') {
        $stmt = $db->prepare("DELETE FROM event_registrations WHERE event_id=? AND user_id=?");
        if ($stmt->execute([$event_id, $user_id])) {
            $message = 'Your registration was cancelled.';
        } else {
            $errors[] = 'Unable to cancel registration.';
        }
    }
}

if (!$errors && $message !== '') {
    $query = [];
    if (isset($_GET['title'])) {
        $query['title'] = $_GET['title'];
    }
    if (isset($_GET['artist'])) {
        $query['artist'] = $_GET['artist'];
    }
    if (isset($_GET['date'])) {
        $query['date'] = $_GET['date'];
    }
    if (isset($_GET['location'])) {
        $query['location'] = $_GET['location'];
    }
    $query['msg'] = $message;
    header('Location: user_dashboard.php?' . http_build_query($query));
    exit();
}

$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

$filters = [];
$params = [];

if ($title !== '') {
    $filters[] = "title LIKE ?";
    $params[] = '%' . $title . '%';
}
if ($artist !== '') {
    $filters[] = "artist LIKE ?";
    $params[] = '%' . $artist . '%';
}
if ($date !== '') {
    $filters[] = "DATE(event_date) = ?";
    $params[] = $date;
}
if ($location !== '') {
    $filters[] = "location LIKE ?";
    $params[] = '%' . $location . '%';
}

$sql = "SELECT * FROM events WHERE status='approved'";
if ($filters) {
    $sql .= " AND " . implode(' AND ', $filters);
}
$sql .= " ORDER BY event_date ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT event_id FROM event_registrations WHERE user_id=?");
$stmt->execute([$user_id]);
$registered_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$registered_map = [];
foreach ($registered_rows as $row) {
    $registered_map[$row['event_id']] = true;
}

$stmt = $db->prepare("
    SELECT e.*, er.registered_at
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    WHERE er.user_id=?
    ORDER BY e.event_date ASC
");
$stmt->execute([$user_id]);
$my_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Concert & Event Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .event-thumb {
            width: 80px;
            height: auto;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row bg-dark text-white py-3 px-4">
            <div class="col d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Attendee Dashboard</h4>
                <div>
                    <span class="me-3"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            </div>
        </div>

        <div class="container mt-4">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Search Approved Events</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Artist</label>
                            <input type="text" name="artist" class="form-control" value="<?php echo htmlspecialchars($artist); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($location); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="user_dashboard.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <h4>Approved Events</h4>
            <div class="table-responsive mb-5">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Poster</th>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($events && count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>
                                        <?php if ($event['image_path']): ?>
                                            <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="Poster" class="event-thumb">
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($event['description']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($event['artist']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td>
                                        <?php if (isset($registered_map[$event['id']])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="cancel_registration">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="register_event">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Register</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No events found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h4 id="my-events">My Registrations</h4>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Registered At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($my_events && count($my_events) > 0): ?>
                            <?php foreach ($my_events as $event): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($event['registered_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="cancel_registration">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">You are not registered for any events.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
