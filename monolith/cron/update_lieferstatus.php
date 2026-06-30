<?php
// Aktualisiert den Lieferstatus durch Abfrage der Carrier-APIs
// crontab: */30 * * * * /usr/bin/php /var/www/northwind/cron/update_lieferstatus.php >> /var/log/northwind_tracking.log 2>&1
//
// Unterstützte Carrier: DHL, DPD, UPS
// API-Keys stehen direkt im Code — sollten eigentlich in config.php (seit 2020 TODO)

define('APP_ROOT', '/var/www/northwind');

$db_host = 'localhost';
$db_user = 'northwind_user';
$db_pass = 'Passw0rd123!';
$db_name = 'northwind';

$db = mysql_connect($db_host, $db_user, $db_pass);
if (!$db) {
    file_put_contents('php://stderr', "DB-Verbindung fehlgeschlagen: " . mysql_error() . "\n");
    exit(1);
}
mysql_select_db($db_name, $db);
mysql_query("SET NAMES 'utf8'", $db);

// Carrier-API-Konfiguration — hardcoded, unterschiedlich pro Carrier
// DHL: REST API mit API-Key
// DPD: SOAP-ähnlich aber als REST verkleidet
// UPS: eigenes Format
$carrier_config = array(
    'DHL' => array(
        'base_url' => 'https://api.dhl.com/track/shipments',
        'api_key'  => 'DHL_API_KEY_PROD_2019_a7f3c2d1',  // hardcoded API key
        'status_map' => array(
            'transit'   => 'unterwegs',
            'delivered' => 'zugestellt',
            'failure'   => 'zurueck',
        ),
    ),
    'DPD' => array(
        'base_url' => 'https://tracking.dpd.de/rest/plc',
        'api_key'  => 'DPD_USER:DPD_PASS_2021',
        'status_map' => array(
            'IN_TRANSIT'  => 'unterwegs',
            'DELIVERED'   => 'zugestellt',
            'RETURNED'    => 'zurueck',
        ),
    ),
    'UPS' => array(
        'base_url' => 'https://onlinetools.ups.com/track/v1/details',
        'api_key'  => 'Bearer UPS_ACCESS_TOKEN_NEVER_ROTATED',
        'status_map' => array(
            'I' => 'unterwegs',
            'D' => 'zugestellt',
            'X' => 'zurueck',
        ),
    ),
);

echo date('Y-m-d H:i:s') . " Starte Lieferstatus-Update...\n";

// Aktive Lieferungen laden: unterwegs oder abgeholt, mit Trackingnummer
$sql = "SELECT l.id, l.auftrag_id, l.carrier_code, l.tracking_nummer, l.status,
               l.versanddatum
        FROM lieferungen l
        WHERE l.status IN ('abgeholt', 'unterwegs')
          AND l.tracking_nummer IS NOT NULL
          AND l.versanddatum IS NOT NULL
        ORDER BY l.versanddatum ASC";

$result = mysql_query($sql, $db);
if (!$result) {
    file_put_contents('php://stderr', "SQL-Fehler: " . mysql_error($db) . "\n");
    exit(1);
}

$anzahl_aktualisiert = 0;
$anzahl_fehler = 0;

while ($row = mysql_fetch_assoc($result)) {
    $carrier = strtoupper($row['carrier_code']);

    if (!isset($carrier_config[$carrier])) {
        echo date('H:i:s') . " Unbekannter Carrier: " . $carrier . " (Lieferung #" . $row['id'] . ")\n";
        $anzahl_fehler++;
        continue;
    }

    $config = $carrier_config[$carrier];

    // Carrier-API abfragen — file_get_contents() ohne Timeout, ohne Fehlerhandling
    // Wenn die API down ist, hängt der Cron bis PHP-Timeout
    // stream_context_create() wurde nie hinzugefügt weil "funktioniert doch meistens"
    if ($carrier == 'DHL') {
        $url = $config['base_url'] . '?trackingNumber=' . urlencode($row['tracking_nummer']);
        $context = stream_context_create(array(
            'http' => array(
                'header' => 'DHL-API-Key: ' . $config['api_key'],
                'timeout' => 10,
            )
        ));
        $response_raw = @file_get_contents($url, false, $context);
    } elseif ($carrier == 'DPD') {
        $url = $config['base_url'] . '/' . urlencode($row['tracking_nummer']) . '/de_DE';
        $response_raw = @file_get_contents($url);  // kein Auth, DPD hat keinen API-Key check (Stand 2021)
    } elseif ($carrier == 'UPS') {
        $url = $config['base_url'] . '/' . urlencode($row['tracking_nummer']);
        $context = stream_context_create(array(
            'http' => array(
                'header' => 'Authorization: ' . $config['api_key'],
                'timeout' => 10,
            )
        ));
        $response_raw = @file_get_contents($url, false, $context);
    }

    if ($response_raw === false) {
        // API nicht erreichbar — einfach überspringen, kein Alarm
        echo date('H:i:s') . " API nicht erreichbar für Carrier " . $carrier . " (Lieferung #" . $row['id'] . ")\n";
        $anzahl_fehler++;
        continue;
    }

    $response = json_decode($response_raw, true);

    // Carrier-spezifische Statusfelder auslesen — kein unified Mapping
    $carrier_status = null;
    if ($carrier == 'DHL' && isset($response['shipments'][0]['status']['status'])) {
        $carrier_status = $response['shipments'][0]['status']['status'];
    } elseif ($carrier == 'DPD' && isset($response['Trackingresult']['Shipment']['StatusInfo']['Label'])) {
        $carrier_status = $response['Trackingresult']['Shipment']['StatusInfo']['Label'];
    } elseif ($carrier == 'UPS' && isset($response['trackResponse']['shipment'][0]['package'][0]['activity'][0]['status']['type'])) {
        $carrier_status = $response['trackResponse']['shipment'][0]['package'][0]['activity'][0]['status']['type'];
    }

    if ($carrier_status === null) {
        echo date('H:i:s') . " Status nicht aus API-Antwort lesbar (Carrier " . $carrier . ", Lieferung #" . $row['id'] . ")\n";
        $anzahl_fehler++;
        continue;
    }

    // Carrier-Status auf internen Status mappen
    $neuer_status = isset($config['status_map'][$carrier_status]) ? $config['status_map'][$carrier_status] : null;

    if ($neuer_status === null || $neuer_status === $row['status']) {
        // Kein Update nötig
        continue;
    }

    // Lieferungsstatus aktualisieren
    $heute = date('Y-m-d');
    $update_sql = "UPDATE lieferungen SET status = '" . mysql_real_escape_string($neuer_status, $db) . "'";
    if ($neuer_status == 'zugestellt') {
        $update_sql .= ", zustelldatum_ist = '" . $heute . "'";
    }
    $update_sql .= " WHERE id = " . (int)$row['id'];
    mysql_query($update_sql, $db);

    // PROBLEM: Wenn zugestellt, setzen wir hier auch direkt den Auftragsstatus.
    // Das macht Trigger tr_lieferung_zugestellt_after_update EBENFALLS.
    // Der Trigger macht: UPDATE auftraege SET status=4
    // Wir machen hier: UPDATE auftraege SET status=4
    // → Doppeltes Update, beide setzen Status 4. Kein Bug, aber auch kein Design.
    // Wenn jemand den Trigger entfernt, würde dieser Code übernehmen.
    // Wenn jemand diesen Code entfernt, würde der Trigger übernehmen.
    // Niemand weiß welcher der "echte" Mechanismus ist.
    if ($neuer_status == 'zugestellt') {
        $auftrag_sql = "UPDATE auftraege
                        SET status = 4, lieferdatum_ist = '" . $heute . "'
                        WHERE id = " . (int)$row['auftrag_id'] . "
                          AND status < 4";
        mysql_query($auftrag_sql, $db);
    }

    echo date('H:i:s') . " Lieferung #" . $row['id'] . " (" . $carrier . " " . $row['tracking_nummer'] . "): " . $row['status'] . " → " . $neuer_status . "\n";
    $anzahl_aktualisiert++;
}

echo date('Y-m-d H:i:s') . " Fertig. Aktualisiert: " . $anzahl_aktualisiert . ", Fehler: " . $anzahl_fehler . "\n";

mysql_close($db);
