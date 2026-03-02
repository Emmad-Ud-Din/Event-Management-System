<?php
/**
 *  displays all approved events and allows guests 
 * to browse and search events without requiring login.
 */

require_once 'config.php';
require_once 'db.php';

// get search query if provided
$search_query = '';
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_query = trim($_GET['search']);
}

// buiild query to fetch approved events
if ($search_query) {
    // search in title, description, artist, and locationn
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as organizer_name 
        FROM events e 
        INNER JOIN users u ON e.organizer_id = u.id 
        WHERE e.status = 'approved' 
        AND (
            e.title LIKE :search 
            OR e.description LIKE :search 
            OR e.artist LIKE :search 
            OR e.location LIKE :search
        )
        ORDER BY e.event_date ASC
    ");
    $search_param = '%' . $search_query . '%';
    $stmt->execute(['search' => $search_param]);
} else {
    // get all approved events
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as organizer_name 
        FROM events e 
        INNER JOIN users u ON e.organizer_id = u.id 
        WHERE e.status = 'approved' 
        ORDER BY e.event_date ASC
    ");
    $stmt->execute();
}

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Concert & Event Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .event-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .event-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .navbar {
            background-color: #343a40;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="events.php">Concert & Event Tracking</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="events.php">Events</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php 
                                if (isAdmin()) echo 'index.php';
                                elseif (isOrganizer()) echo 'organizer_dashboard.php';
                                else echo 'user_dashboard.php';
                            ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user_register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-4">Upcoming Events</h1>
                
                <!-- Search Form -->
                <form method="GET" action="events.php" class="mb-4">
                    <div class="input-group">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search events by title, artist, location, or description..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if ($search_query): ?>
                            <a href="events.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($search_query): ?>
                    <p class="text-muted">Search results for: <strong><?php echo htmlspecialchars($search_query); ?></strong></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Events Grid -->
        <?php if ($events && count($events) > 0): ?>
            <div class="row">
                <?php foreach ($events as $event): ?>
                    <div class="col-md-4">
                        <div class="card event-card">
                            <?php if ($event['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($event['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($event['title']); ?>" 
                                     class="card-img-top event-image">
                            <?php else: ?>
                                <div class="card-img-top event-image bg-secondary d-flex align-items-center justify-content-center">
                                    <span class="text-white">No Image</span>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <?php if ($event['artist']): ?>
                                    <p class="card-text text-muted">
                                        <strong>Artist:</strong> <?php echo htmlspecialchars($event['artist']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($event['description']): ?>
                                    <p class="card-text">
                                        <?php 
                                        $desc = htmlspecialchars($event['description']);
                                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($event['event_date'])); ?><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?><br>
                                        <strong>Organizer:</strong> <?php echo htmlspecialchars($event['organizer_name']); ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <?php if ($search_query): ?>
                    No events found matching your search criteria.
                <?php else: ?>
                    No approved events available at the moment. Please check back later.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

