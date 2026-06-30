
</div><!-- /.container-fluid -->

<footer class="footer" style="margin-top:40px; padding:20px 0; border-top:1px solid #eee; color:#999; font-size:12px;">
  <div class="container-fluid">
    &copy; <?php echo date('Y'); ?> Northwind Logistics GmbH &mdash; Alle Rechte vorbehalten
  </div>
</footer>

<?php
// DEBUG-Modus: Session-Inhalt anzeigen
// TODO: vor Go-Live entfernen (steht hier seit 2016)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<div class="container-fluid"><pre style="background:#f5f5f5;padding:10px;font-size:11px;">';
    echo '<strong>$_SESSION:</strong>' . "\n";
    debug($_SESSION);
    echo '</pre></div>';
}
?>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
