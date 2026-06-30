<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Northwind Logistics GmbH</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; }
        .navbar-brand { font-weight: bold; }
        .lagerbestand-niedrig { color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>
<nav class="navbar navbar-default navbar-fixed-top">
  <div class="container-fluid">
    <div class="navbar-header">
      <a class="navbar-brand" href="index.php">Northwind Logistics</a>
    </div>
    <ul class="nav navbar-nav">
      <li <?php if (isset($_GET['page']) && $_GET['page']=='kunden') echo 'class="active"'; ?>>
        <a href="index.php?page=kunden">Kunden</a></li>
      <li <?php if (isset($_GET['page']) && $_GET['page']=='artikel') echo 'class="active"'; ?>>
        <a href="index.php?page=artikel">Artikel</a></li>
      <li <?php if (isset($_GET['page']) && $_GET['page']=='auftraege') echo 'class="active"'; ?>>
        <a href="index.php?page=auftraege">Aufträge</a></li>
      <li <?php if (isset($_GET['page']) && $_GET['page']=='lieferungen') echo 'class="active"'; ?>>
        <a href="index.php?page=lieferungen">Lieferungen</a></li>
      <li <?php if (isset($_GET['page']) && $_GET['page']=='rechnungen') echo 'class="active"'; ?>>
        <a href="index.php?page=rechnungen">Rechnungen</a></li>
      <?php if (isset($_SESSION['rolle']) && $_SESSION['rolle'] == 'admin'): ?>
      <li <?php if (isset($_GET['page']) && $_GET['page']=='admin_berichte') echo 'class="active"'; ?>>
        <a href="index.php?page=admin_berichte">Berichte</a></li>
      <?php endif; ?>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="#"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : ''; ?></a></li>
      <li><a href="index.php?page=logout">Abmelden</a></li>
    </ul>
  </div>
</nav>
<div class="container-fluid">

<?php
// Erfolgsmeldungen und Fehlermeldungen aus Session anzeigen
if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="modal">&times;</button>
    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
  </div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="modal">&times;</button>
    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
  </div>
<?php endif; ?>
