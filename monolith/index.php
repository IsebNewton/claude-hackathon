<?php
require_once 'init.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Login-Seite braucht keine Session
if ($page != 'login') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php?page=login');
        exit;
    }
}

if ($page == 'login') {
    require_once 'pages/login.php';
} elseif ($page == 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
} elseif ($page == 'dashboard') {
    require_once 'pages/dashboard.php';
} elseif ($page == 'kunden') {
    require_once 'pages/kunden.php';
} elseif ($page == 'kunden_edit') {
    require_once 'pages/kunden_edit.php';
} elseif ($page == 'artikel') {
    require_once 'pages/artikel.php';
} elseif ($page == 'artikel_edit') {
    require_once 'pages/artikel_edit.php';
} elseif ($page == 'auftraege') {
    require_once 'pages/auftraege.php';
} elseif ($page == 'auftrag_neu') {
    require_once 'pages/auftrag_neu.php';
} elseif ($page == 'auftrag_detail') {
    require_once 'pages/auftrag_detail.php';
} elseif ($page == 'lieferungen') {
    require_once 'pages/lieferungen.php';
} elseif ($page == 'lieferung_detail') {
    require_once 'pages/lieferung_detail.php';
} elseif ($page == 'rechnungen') {
    require_once 'pages/rechnungen.php';
} elseif ($page == 'rechnung_detail') {
    require_once 'pages/rechnung_detail.php';
} elseif ($page == 'rechnung_zahlung') {
    require_once 'pages/rechnung_zahlung.php';
} elseif ($page == 'admin_berichte') {
    // Nur Admins duerfen hier rein
    if ($_SESSION['rolle'] != 'admin') {
        die('Kein Zugriff');
    }
    require_once 'pages/admin_berichte.php';
} else {
    // Unbekannte Seite — einfach Dashboard anzeigen
    require_once 'pages/dashboard.php';
}
