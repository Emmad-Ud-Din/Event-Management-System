<?php

require_once 'config.php';
//  only admin users can access this page
requireAdmin();


// Get total organizers count
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role='organizer'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_organizers = $row['count'];

// Get pending organizers count
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role='organizer' AND status='pending'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$pending_organizers = $row['count'];

// Get total events count
$stmt = $db->query("SELECT COUNT(*) as count FROM events");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_events = $row['count'];

// Get pending events count
$stmt = $db->query("SELECT COUNT(*) as count FROM events WHERE status='pending'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$pending_events = $row['count'];

// Get total users count
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role='user'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_users = $row['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Concert & Event Tracking</title>
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
    </style>
</head>
<body>
    <button class="menu-toggle-btn" id="menuToggleBtn" onclick="toggleSidebar()">
        ☰
    </button>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar" id="sidebar">
                <h4 class="text-white p-3">
                    <button class="menu-toggle-btn-inside" id="menuToggleBtnInside" onclick="toggleSidebar()">
                        ☰
                    </button>
                    <span class="panel-title">Admin Panel</span>
                </h4>
                <a href="index.php" class="active">Dashboard</a>
                <a href="adminFunc.php?section=organizers">Manage Organizers</a>
                <a href="adminFunc.php?section=events">Manage Events</a>
                <a href="adminFunc.php?section=registrations">Registered Events</a>
                <a href="logout.php" class="text-danger">Logout</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4 content-area" id="contentArea">
                <h2>Admin Dashboard</h2>
                <p class="text-muted">Welcome, <?php 
                if (isset($_SESSION['full_name'])) {
                    echo htmlspecialchars($_SESSION['full_name']);
                } else {
                    echo 'Admin';
                }
                ?>!</p>
                
                <div class="row mt-4">
                    <!-- Organizers Statistics Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Organizers</h5>
                                <!-- Total number of registered organizers -->
                                <p class="card-text">Total: <?php echo $total_organizers; ?></p>
                                <!-- Number of organizers waiting for approval -->
                                <p class="card-text text-warning">Pending: <?php echo $pending_organizers; ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Events Statistics Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Events</h5>
                                <!-- Total number of events in the system -->
                                <p class="card-text">Total: <?php echo $total_events; ?></p>
                                <!-- Number of events waiting for approval -->
                                <p class="card-text text-warning">Pending: <?php echo $pending_events; ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Users Statistics Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Users</h5>
                                <!-- Total number of normal users (attendees) -->
                                <p class="card-text">Total: <?php echo $total_users; ?></p>
                            </div>
                        </div>
                    </div>
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

