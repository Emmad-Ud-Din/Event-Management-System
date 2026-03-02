<?php
require_once 'config.php';
requireAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function post(string $k, $default = null) {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $default;
}
function get(string $k, $default = null) {
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $default;
}
function valid_int($v): ?int {
    $x = filter_var($v, FILTER_VALIDATE_INT);
    return ($x === false) ? null : (int)$x;
}
function whitelist(string $value, array $allowed, string $default): string {
    return in_array($value, $allowed, true) ? $value : $default;
}
function ensure_csrf_or_die(): void {
    $t = post('csrf_token', '');
    if ($t === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(403);
        exit('CSRF check failed.');
    }
}

$pdo = $db;

$section = whitelist(get('section', 'organizers'), ['organizers', 'events', 'registrations'], 'organizers');
$message = '';
$error   = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ensure_csrf_or_die();

        $action = whitelist((string)post('action', ''), [
            'approve', 'reject', 'deactivate', 'delete_organizer',
            'update_event', 'delete_event', 'approve_event', 'reject_event'
        ], '');

        if ($action === '') {
            $error = 'Invalid action.';
        }

        if (in_array($action, ['approve', 'reject', 'deactivate', 'delete_organizer'], true)) {
            $section = 'organizers';

            $org_id = valid_int(post('organizer_id'));
            if ($org_id === null) {
                $error = 'Invalid organizer id.';
            } else {
                if ($action === 'delete_organizer') {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'organizer'");
                    $stmt->execute([':id' => $org_id]);
                    $message = 'Organizer deleted.';
                } else {
                    $new_status = [
                        'approve'    => 'active',
                        'reject'     => 'rejected',
                        'deactivate' => 'deactivated',
                    ][$action];

                    $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id AND role = 'organizer'");
                    $stmt->execute([':status' => $new_status, ':id' => $org_id]);
                    $message = "Organizer status updated to '{$new_status}'.";
                }
            }
        }

        if (in_array($action, ['update_event', 'delete_event', 'approve_event', 'reject_event'], true)) {
            $section = 'events';

            $event_id = valid_int(post('event_id'));
            if ($event_id === null) {
                $error = 'Invalid event id.';
            } else if ($action === 'delete_event') {
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
                $stmt->execute([':id' => $event_id]);
                $message = 'Event deleted.';
            } else if ($action === 'approve_event' || $action === 'reject_event') {
                $new_status = $action === 'approve_event' ? 'approved' : 'rejected';
                $stmt = $pdo->prepare("UPDATE events SET status = :status WHERE id = :id");
                $stmt->execute([':status' => $new_status, ':id' => $event_id]);
                $message = "Event status updated to '{$new_status}'.";
            } else {
                $title       = (string)post('title', '');
                $description = (string)post('description', '');
                $artist      = (string)post('artist', '');
                $event_date  = (string)post('event_date', '');
                $location    = (string)post('location', '');
                $status      = whitelist((string)post('status', 'pending'),
                                ['pending', 'approved', 'rejected'], 'pending');

                if ($title === '' || strlen($title) > 200)            $error = 'Title is required (max 200 chars).';
                else if (strlen($artist) > 150)                       $error = 'Artist max length is 150.';
                else if (strlen($location) > 200)                     $error = 'Location max length is 200.';
                else if (strlen($description) > 2000)                 $error = 'Description max length is 2000.';
                else {
                    $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $event_date);
                    if (!$dt) {
                        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);
                    }
                    if (!$dt) {
                        $error = 'Invalid date format. Use YYYY-MM-DD HH:MM.';
                    } else {
                        $event_date = $dt->format('Y-m-d H:i:s');
                    }
                }

                if ($error === '') {
                    $stmt = $pdo->prepare("
                        UPDATE events
                        SET title = :title,
                            description = :description,
                            artist = :artist,
                            event_date = :event_date,
                            location = :location,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':title'       => $title,
                        ':description' => $description,
                        ':artist'      => $artist,
                        ':event_date'  => $event_date,
                        ':location'    => $location,
                        ':status'      => $status,
                        ':id'          => $event_id,
                    ]);
                    $message = 'Event updated.';
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log("adminFunc error: " . $e->getMessage());
    $error = 'An unexpected error occurred.';
}

$organizers = [];
$events     = [];
$edit_event = null;
$registrations = [];

try {
    if ($section === 'organizers') {
        $organizers = $pdo->query("
            SELECT id, full_name, email, status, created_at
            FROM users
            WHERE role = 'organizer'
            ORDER BY created_at DESC
        ")->fetchAll();
    } else if ($section === 'registrations') {
        $registrations = $pdo->query("
            SELECT 
                er.id AS registration_id,
                er.registered_at,
                e.id AS event_id,
                e.title AS event_title,
                e.event_date,
                e.location AS event_location,
                u.id AS user_id,
                u.full_name AS user_name,
                u.email AS user_email
            FROM event_registrations er
            JOIN events e ON er.event_id = e.id
            JOIN users u ON er.user_id = u.id
            ORDER BY er.registered_at DESC
        ")->fetchAll();
    } else {
        $events = $pdo->query("
            SELECT e.*, u.full_name AS organizer_name, u.email AS organizer_email
            FROM events e
            JOIN users u ON e.organizer_id = u.id
            ORDER BY e.created_at DESC
        ")->fetchAll();

        $edit_id = valid_int(get('edit_event_id'));
        if ($edit_id !== null) {
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
            $stmt->execute([':id' => $edit_id]);
            $edit_event = $stmt->fetch() ?: null;
        }
    }
} catch (Throwable $e) {
    error_log("adminFunc fetch error: " . $e->getMessage());
    $error = $error ?: 'Failed to load data.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Functions - Concert & Event Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 250px; /* expanded width */
            z-index: 1000;
            overflow: hidden;
        }
        /* collapsed: keep a thin column visible */
        .sidebar.collapsed {
            width: 56px; /* thin column when closed */
        }

        /* hide links and title text when collapsed; keep column filled with background */
        .sidebar.collapsed a {
            display: none;
        }
        .sidebar.collapsed .panel-title {
            display: none;
        }
        .sidebar.collapsed h4 {
            padding: 8px 0;
            text-align: center;
        }

        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .sidebar a.active {
            background-color: #007bff;
        }
        .menu-toggle-btn {
            position: fixed;
            top: 12px;
            left: 15px; /* aligns inside the thin column when collapsed  */
            z-index: 1001;
            background-color: #343a40;
            border: none;
            color: white;
            padding: 8px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .menu-toggle-btn:hover {
            background-color: #495057;
        }
        .sidebar h4 {
            margin-top: 0;
            padding: 15px 50px 15px 18px;
            position: relative;
            color: #fff;
        }
        /* place the inside toggle to the right of the title */
        .sidebar .menu-toggle-btn-inside {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 1002;
            background-color: transparent;
            border: none;
            color: white;
            padding: 5px 8px;
            cursor: pointer;
            font-size: 20px;
        }
        .sidebar .menu-toggle-btn-inside:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        /* when collapsed, center the inside button in the thin column */
        .sidebar.collapsed .menu-toggle-btn-inside {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
            top: 8px;
            display: block;
        }

        .content-area {
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
            margin-left: 250px; /* space for expanded sidebar */
        }
        @media (min-width: 768px) {
            /* on wider screens keep fixed widths */
            .sidebar {
                position: fixed;
            }
            .sidebar.collapsed {
                width: 56px;
            }
            .menu-toggle-btn {
                left: 15px;
            }
            .content-area { margin-left: 250px; }
        }
        @media (max-width: 767px) {
            .menu-toggle-btn {
                left: 15px;
            }
        }
        @media (max-width: 767px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                width: 250px;
            }
            /* on small screens, collapsed hides sidebar completely */
            .sidebar.collapsed {
                transform: translateX(-100%);
            }
            .content-area {
                margin-left: 0;
            }
        }
        /* when sidebar is collapsed adjust content spacing */
        .sidebar.collapsed ~ .container-fluid .content-area,
        .sidebar.collapsed + .container-fluid .content-area {
            margin-left: 56px;
        }
    </style>
</head>
<body>
    <button class="menu-toggle-btn" id="menuToggleBtn" onclick="toggleSidebar()">
        ☰
    </button>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar" id="sidebar">
                <h4 class="text-white p-3">
                    <button class="menu-toggle-btn-inside" id="menuToggleBtnInside" onclick="toggleSidebar()">
                        ☰
                    </button>
                    <span class="panel-title">Admin Panel</span>
                </h4>
                <a href="index.php">Dashboard</a>
                <a href="adminFunc.php?section=organizers" class="<?php echo $section === 'organizers' ? 'active' : ''; ?>">Manage Organizers</a>
                <a href="adminFunc.php?section=events" class="<?php echo $section === 'events' ? 'active' : ''; ?>">Manage Events</a>
                <a href="adminFunc.php?section=registrations" class="<?php echo $section === 'registrations' ? 'active' : ''; ?>">Registered Events</a>
                <a href="logout.php" class="text-danger">Logout</a>
            </div>

            <div class="col-md-10 p-4 content-area" id="contentArea">
                <?php if ($message !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo h($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo h($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($section === 'organizers'): ?>
                    <h2>Manage Organizers</h2>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">All Registered Organizers</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Registered Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($organizers && count($organizers) > 0): ?>
                                            <?php foreach ($organizers as $o): ?>
                                                <tr>
                                                    <td><?php echo h((string)$o['id']); ?></td>
                                                    <td><?php echo h((string)$o['full_name']); ?></td>
                                                    <td><?php echo h((string)$o['email']); ?></td>
                                                    <td>
                                                        <?php
                                                        $status = (string)$o['status'];
                                                        $badgeClass = $status === 'active' ? 'bg-success' :
                                                            ($status === 'pending' ? 'bg-warning' :
                                                            ($status === 'rejected' ? 'bg-danger' : 'bg-secondary'));
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime((string)$o['created_at'])); ?></td>
                                                    <td>
                                                        <?php $oid = (int)$o['id']; ?>
                                                        <?php if ($status === 'pending'): ?>
                                                            <form method="post" action="adminFunc.php?section=organizers" style="display:inline;" onsubmit="return confirm('Approve this organizer?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <input type="hidden" name="organizer_id" value="<?php echo $oid; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                            </form>
                                                            <form method="post" action="adminFunc.php?section=organizers" style="display:inline;" onsubmit="return confirm('Reject this organizer?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="organizer_id" value="<?php echo $oid; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($status === 'active'): ?>
                                                            <form method="post" action="adminFunc.php?section=organizers" style="display:inline;" onsubmit="return confirm('Deactivate this organizer?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="organizer_id" value="<?php echo $oid; ?>">
                                                                <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="post" action="adminFunc.php?section=organizers" style="display:inline;" onsubmit="return confirm('Delete this organizer?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="delete_organizer">
                                                            <input type="hidden" name="organizer_id" value="<?php echo $oid; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No organizers found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($section === 'events'): ?>
                    <h2>Manage Events</h2>

                    <?php if ($edit_event): ?>
                        <?php
                        $event_date_value = '';
                        if (!empty($edit_event['event_date'])) {
                            $event_date_value = date('Y-m-d\TH:i', strtotime((string)$edit_event['event_date']));
                        }
                        ?>
                        <div class="card mt-3 mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Edit Event #<?php echo h((string)$edit_event['id']); ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="update_event">
                                    <input type="hidden" name="event_id" value="<?php echo h((string)$edit_event['id']); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" value="<?php echo h((string)$edit_event['title']); ?>" maxlength="200" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3" maxlength="2000"><?php echo h((string)$edit_event['description']); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Artist</label>
                                        <input type="text" name="artist" class="form-control" value="<?php echo h((string)$edit_event['artist']); ?>" maxlength="150">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Event Date</label>
                                        <input type="datetime-local" name="event_date" class="form-control" value="<?php echo h($event_date_value); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" name="location" class="form-control" value="<?php echo h((string)$edit_event['location']); ?>" maxlength="200">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="pending" <?php echo $edit_event['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $edit_event['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $edit_event['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Save</button>
                                        <a href="adminFunc.php?section=events" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">All Events Created by Organizers</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Artist</th>
                                            <th>Organizer</th>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($events && count($events) > 0): ?>
                                            <?php foreach ($events as $e): ?>
                                                <tr>
                                                    <td><?php echo h((string)$e['id']); ?></td>
                                                    <td><?php echo h((string)$e['title']); ?></td>
                                                    <td><?php echo $e['artist'] !== '' ? h((string)$e['artist']) : 'N/A'; ?></td>
                                                    <td><?php echo h((string)$e['organizer_name']); ?><br><small class="text-muted"><?php echo h((string)$e['organizer_email']); ?></small></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime((string)$e['event_date'])); ?></td>
                                                    <td><?php echo h((string)$e['location']); ?></td>
                                                    <td>
                                                        <?php
                                                        $status = (string)$e['status'];
                                                        $badgeClass = $status === 'approved' ? 'bg-success' :
                                                            ($status === 'pending' ? 'bg-warning' : 'bg-danger');
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($status === 'pending'): ?>
                                                            <form method="post" action="adminFunc.php?section=events" style="display:inline;" onsubmit="return confirm('Approve this event?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="approve_event">
                                                                <input type="hidden" name="event_id" value="<?php echo h((string)$e['id']); ?>">
                                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                            </form>
                                                            <form method="post" action="adminFunc.php?section=events" style="display:inline;" onsubmit="return confirm('Reject this event?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="reject_event">
                                                                <input type="hidden" name="event_id" value="<?php echo h((string)$e['id']); ?>">
                                                                <button type="submit" class="btn btn-sm btn-warning">Reject</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <a href="adminFunc.php?section=events&edit_event_id=<?php echo h((string)$e['id']); ?>" class="btn btn-sm btn-primary">Edit</a>
                                                        <form method="post" action="adminFunc.php?section=events" style="display:inline;" onsubmit="return confirm('Delete this event?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="delete_event">
                                                            <input type="hidden" name="event_id" value="<?php echo h((string)$e['id']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No events found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($section === 'registrations'): ?>
                    <h2>Registered Events</h2>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">All Event Registrations</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Registration ID</th>
                                            <th>Event</th>
                                            <th>Event Date</th>
                                            <th>Location</th>
                                            <th>User Name</th>
                                            <th>User Email</th>
                                            <th>Registered At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($registrations && count($registrations) > 0): ?>
                                            <?php foreach ($registrations as $reg): ?>
                                                <tr>
                                                    <td><?php echo h((string)$reg['registration_id']); ?></td>
                                                    <td>
                                                        <strong><?php echo h((string)$reg['event_title']); ?></strong><br>
                                                        <small class="text-muted">Event ID: <?php echo h((string)$reg['event_id']); ?></small>
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime((string)$reg['event_date'])); ?></td>
                                                    <td><?php echo h((string)$reg['event_location']); ?></td>
                                                    <td><?php echo h((string)$reg['user_name']); ?></td>
                                                    <td><?php echo h((string)$reg['user_email']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime((string)$reg['registered_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No registrations found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuToggleBtn');
            const menuBtnInside = document.getElementById('menuToggleBtnInside');
            sidebar.classList.toggle('collapsed');

            // Update content area margin and show/hide buttons based on sidebar state
            const content = document.getElementById('contentArea');
            const isCollapsed = sidebar.classList.contains('collapsed');

            if (window.innerWidth >= 768) {
                // desktop: use the inside button (keeps icon at top of thin column when collapsed,
                // and to the right of title when expanded hide the fixed button.
                menuBtn.style.display = 'none';
                if (isCollapsed) {
                    menuBtnInside.style.display = 'block';
                    content.style.marginLeft = '56px';
                } else {
                    menuBtnInside.style.display = 'block';
                    content.style.marginLeft = '250px';
                }
            } else {
                // mobile: keep fixed button visible to toggle the sidebar
                menuBtn.style.display = 'block';
                menuBtnInside.style.display = 'none';
                if (isCollapsed) {
                    content.style.marginLeft = '0';
                } else {
                    content.style.marginLeft = '250px';
                }
            }
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuToggleBtn');
            const isMobile = window.innerWidth < 768;
            
            if (isMobile && !sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                }
            }
        });
        
        // Adjust button visibility on window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuToggleBtn');
            const menuBtnInside = document.getElementById('menuToggleBtnInside');
            const content = document.getElementById('contentArea');
            if (window.innerWidth >= 768) {
                // desktop: always use inside button
                menuBtn.style.display = 'none';
                menuBtnInside.style.display = sidebar.classList.contains('collapsed') ? 'block' : 'block';
                content.style.marginLeft = sidebar.classList.contains('collapsed') ? '56px' : '250px';
            } else {
                menuBtn.style.display = 'block';
                menuBtnInside.style.display = 'none';
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = '0';
                } else {
                    content.style.marginLeft = '250px';
                }
            }
        });
        
        // Set initial button visibility
        window.addEventListener('load', function() {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuToggleBtn');
            const menuBtnInside = document.getElementById('menuToggleBtnInside');
            const content = document.getElementById('contentArea');
            if (window.innerWidth >= 768) {
                menuBtn.style.display = 'none';
                menuBtnInside.style.display = sidebar.classList.contains('collapsed') ? 'block' : 'block';
                content.style.marginLeft = sidebar.classList.contains('collapsed') ? '56px' : '250px';
            } else {
                menuBtn.style.display = 'block';
                menuBtnInside.style.display = 'none';
                content.style.marginLeft = sidebar.classList.contains('collapsed') ? '0' : '250px';
            }
        });
    </script>
</body>
</html>
