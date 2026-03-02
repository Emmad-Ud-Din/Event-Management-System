Concert & Event Tracking System
A role-based web application for managing event listings, organizer approvals, and attendee registrations.

Key Features
Admin Dashboard: High-level statistics for organizers, events, and users.

Organizer Moderation: Admins can approve, reject, or deactivate organizer accounts.

Event Management: Full control over event listings, including a status-based approval workflow (Pending, Approved, Rejected).

Registration Tracking: A dedicated view for monitoring all user sign-ups for specific events.

Public Event Search: Guests can browse and search approved events by title, artist, or location without logging in.

Security & Technical Details
CSRF Protection: Secure token validation on all administrative actions to prevent unauthorized requests.

Authentication Middleware: Role-specific access control (Admin, Organizer, User) to protect sensitive routes.

Database Integration: Uses PHP Data Objects (PDO) for secure, prepared-statement interactions.

Responsive UI: Built with Bootstrap 5 and featuring a collapsible sidebar for the admin panel.

Core Files
adminFunc.php: Main logic for managing the organizer and event lifecycle.

config.php: Centralized session handling and role-based permission checks.

events.php: The public-facing searchable event gallery.

index.php: The administrative statistics dashboard.

login.php: Unified secure login portal.

Setup
Database: Ensure your MySQL database includes tables for users, events, and event_registrations.

Configuration: Update db.php with your local database credentials.

Demo Access: Use admin@concert.com / admin123 for initial testing.
