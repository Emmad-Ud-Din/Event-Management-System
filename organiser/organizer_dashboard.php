<?php
require_once 'config.php';
requireOrganizer();

function normalizeDateTime($value)
{
    if ($value === '') {
        return '';
    }
    $normalized = str_replace('T', ' ', $value);
    if (strlen($normalized) === 16) {
        $normalized .= ':00';
    }
    return $normalized;
}

$organizer_id = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT full_name, status FROM users WHERE id=? AND role='organizer'");
$stmt->execute([$organizer_id]);
$organizer = $stmt->fetch(PDO::FETCH_ASSOC);

$organizer_name = $organizer ? $organizer['full_name'] : 'Organizer';
$organizer_status = $organizer ? $organizer['status'] : 'pending';
$is_active = $organizer_status === 'active';

$errors = [];
$message = '';
$new_title = '';
$new_description = '';
$new_artist = '';
$new_event_date = '';
$new_location = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_active) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else {
        $action = '';
    }

    if ($action === 'add_event') {
        if (isset($_POST['title'])) {
            $new_title = trim($_POST['title']);
        }
        if (isset($_POST['description'])) {
            $new_description = trim($_POST['description']);
        }
        if (isset($_POST['artist'])) {
            $new_artist = trim($_POST['artist']);
        }
        if (isset($_POST['event_date'])) {
            $new_event_date = $_POST['event_date'];
        }
        if (isset($_POST['location'])) {
            $new_location = trim($_POST['location']);
        }

        if ($new_title === '') {
            $errors[] = 'Title is required.';
        }
        if ($new_event_date === '') {
            $errors[] = 'Event date is required.';
        }
        if ($new_location === '') {
            $errors[] = 'Location is required.';
        }

        $image_path = '';
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Event poster image is required.';
        } else {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Image must be JPG, PNG, or GIF.';
            }
        }

        if (!$errors) {
            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $filename = uniqid('event_', true) . '.' . $ext;
            $destination = $upload_dir . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $errors[] = 'Failed to upload image.';
            } else {
                $image_path = 'uploads/' . $filename;
            }
        }

        if (!$errors) {
            $event_date = normalizeDateTime($new_event_date);
            $stmt = $db->prepare("INSERT INTO events (organizer_id, title, description, artist, event_date, location, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            if ($stmt->execute([$organizer_id, $new_title, $new_description, $new_artist, $event_date, $new_location, $image_path])) {
                $message = 'Event added. It is pending admin approval.';
                $new_title = '';
                $new_description = '';
                $new_artist = '';
                $new_event_date = '';
                $new_location = '';
            } else {
                $errors[] = 'Failed to add event.';
            }
        }
    }

    if ($action === 'update_event') {
        if (isset($_POST['event_id'])) {
            $event_id = intval($_POST['event_id']);
        } else {
            $event_id = 0;
        }
        if (isset($_POST['title'])) {
            $title = trim($_POST['title']);
        } else {
            $title = '';
        }
        if (isset($_POST['description'])) {
            $description = trim($_POST['description']);
        } else {
            $description = '';
        }
        if (isset($_POST['artist'])) {
            $artist = trim($_POST['artist']);
        } else {
            $artist = '';
        }
        if (isset($_POST['event_date'])) {
            $event_date = $_POST['event_date'];
        } else {
            $event_date = '';
        }
        if (isset($_POST['location'])) {
            $location = trim($_POST['location']);
        } else {
            $location = '';
        }

        if ($event_id <= 0) {
            $errors[] = 'Invalid event.';
        }
        if ($title === '' || $event_date === '' || $location === '') {
            $errors[] = 'Title, date, and location are required.';
        }

        $stmt = $db->prepare("SELECT image_path FROM events WHERE id=? AND organizer_id=?");
        $stmt->execute([$event_id, $organizer_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $errors[] = 'Event not found.';
        }

        $image_path = $existing ? $existing['image_path'] : '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Image upload failed.';
            } else {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    $errors[] = 'Image must be JPG, PNG, or GIF.';
                } else {
                    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0775, true);
                    }
                    $filename = uniqid('event_', true) . '.' . $ext;
                    $destination = $upload_dir . DIRECTORY_SEPARATOR . $filename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                        $image_path = 'uploads/' . $filename;
                    } else {
                        $errors[] = 'Failed to upload image.';
                    }
                }
            }
        }

        if (!$errors) {
            $event_date = normalizeDateTime($event_date);
            $stmt = $db->prepare("UPDATE events SET title=?, description=?, artist=?, event_date=?, location=?, image_path=? WHERE id=? AND organizer_id=?");
            if ($stmt->execute([$title, $description, $artist, $event_date, $location, $image_path, $event_id, $organizer_id])) {
                $message = 'Event updated successfully.';
            } else {
                $errors[] = 'Failed to update event.';
            }
        }
    }

    if ($action === 'delete_event') {
        if (isset($_POST['event_id'])) {
            $event_id = intval($_POST['event_id']);
        } else {
            $event_id = 0;
        }

        if ($event_id > 0) {
            $stmt = $db->prepare("DELETE FROM events WHERE id=? AND organizer_id=?");
            if ($stmt->execute([$event_id, $organizer_id])) {
                $message = 'Event deleted.';
            } else {
                $errors[] = 'Failed to delete event.';
            }
        } else {
            $errors[] = 'Invalid event.';
        }
    }
}

if (!$errors && $message !== '') {
    if (isset($_GET['status'])) {
        $status_param = $_GET['status'];
    } else {
        $status_param = 'all';
    }
    header('Location: organizer_dashboard.php?status=' . urlencode($status_param) . '&msg=' . urlencode($message));
    exit();
}

if (isset($_GET['status'])) {
    $status_filter = $_GET['status'];
} else {
    $status_filter = 'all';
}

$allowed_filters = ['all', 'approved', 'pending', 'rejected'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

if ($status_filter === 'all') {
    $stmt = $db->prepare("SELECT * FROM events WHERE organizer_id=? ORDER BY created_at DESC");
    $stmt->execute([$organizer_id]);
} else {
    $stmt = $db->prepare("SELECT * FROM events WHERE organizer_id=? AND status=? ORDER BY created_at DESC");
    $stmt->execute([$organizer_id, $status_filter]);
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - Concert & Event Tracking</title>
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
            left: 15px; /* aligns inside the thin column when collapsed (mobile fallback) */
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
        .event-thumb {
            width: 70px;
            height: auto;
            border-radius: 4px;
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
                    <span class="panel-title">Organizer Panel</span>
                </h4>
                <a href="organizer_dashboard.php" class="active">My Events</a>
                <a href="logout.php" class="text-danger">Logout</a>
            </div>
            <div class="col-md-10 p-4 content-area" id="contentArea">
                <h2>Organizer Dashboard</h2>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($organizer_name); ?>.</p>

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

                <?php if (!$is_active): ?>
                    <div class="alert alert-warning">
                        Your organizer status is <strong><?php echo htmlspecialchars($organizer_status); ?></strong>.
                        You can view your events, but you cannot add or edit events until the admin approves your account.
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Create New Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_event">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($new_title); ?>" <?php echo $is_active ? 'required' : 'disabled'; ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Artist</label>
                                    <input type="text" name="artist" class="form-control" value="<?php echo htmlspecialchars($new_artist); ?>" <?php echo $is_active ? '' : 'disabled'; ?>>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" <?php echo $is_active ? '' : 'disabled'; ?>><?php echo htmlspecialchars($new_description); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Event Date</label>
                                    <input type="datetime-local" name="event_date" class="form-control" value="<?php echo htmlspecialchars($new_event_date); ?>" <?php echo $is_active ? 'required' : 'disabled'; ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($new_location); ?>" <?php echo $is_active ? 'required' : 'disabled'; ?>>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Poster (Image)</label>
                                <input type="file" name="image" class="form-control" accept="image/*" <?php echo $is_active ? 'required' : 'disabled'; ?>>
                            </div>
                            <button type="submit" class="btn btn-primary" <?php echo $is_active ? '' : 'disabled'; ?>>Add Event</button>
                        </form>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h4 class="mb-0">My Events</h4>
                    <form method="GET" class="d-flex">
                        <label class="me-2 align-self-center">Filter:</label>
                        <select name="status" class="form-select me-2" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Poster</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td>
                                            <?php
                                            $status = $event['status'];
                                            $badgeClass = $status === 'approved' ? 'bg-success' : ($status === 'pending' ? 'bg-warning' : 'bg-danger');
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                        </td>
                                        <td>
                                            <a href="organizer_attendees.php?event_id=<?php echo $event['id']; ?>" class="btn btn-sm btn-secondary">Attendees</a>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $event['id']; ?>" <?php echo $is_active ? '' : 'disabled'; ?>>Edit</button>
                                            <form method="POST" action="organizer_dashboard.php?status=<?php echo urlencode($status_filter); ?>" style="display:inline;" onsubmit="return confirm('Delete this event?');">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <input type="hidden" name="action" value="delete_event">
                                                <button type="submit" class="btn btn-sm btn-danger" <?php echo $is_active ? '' : 'disabled'; ?>>Delete</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editModal<?php echo $event['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" enctype="multipart/form-data" action="organizer_dashboard.php?status=<?php echo urlencode($status_filter); ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Event</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                        <input type="hidden" name="action" value="update_event">
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Title</label>
                                                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Artist</label>
                                                                <input type="text" name="artist" class="form-control" value="<?php echo htmlspecialchars($event['artist']); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($event['description']); ?></textarea>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Event Date</label>
                                                                <input type="datetime-local" name="event_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Location</label>
                                                                <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($event['location']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Replace Poster (optional)</label>
                                                            <input type="file" name="image" class="form-control" accept="image/*">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No events found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                // and to the right of title when expanded). hide the fixed button.
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
