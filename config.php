<?php
session_start();
require_once 'db.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isOrganizer()
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'organizer';
}

function isUser()
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: login.php');
        exit();
    }
}

function requireOrganizer()
{
    if (!isOrganizer()) {
        header('Location: login.php');
        exit();
    }
}

function requireUser()
{
    if (!isUser()) {
        header('Location: login.php');
        exit();
    }
}
