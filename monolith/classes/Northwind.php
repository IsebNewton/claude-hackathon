<?php
/**
 * Northwind - Haupt-Service-Klasse
 *
 * Erstellt: 2009 als einfacher DB-Helper.
 * Heute: Gott-Klasse mit 30+ Methoden über alle Domains.
 *
 * Aufbau (historisch gewachsen):
 *  - Kunden-Methoden (2009)
 *  - Artikel-Methoden (2010)
 *  - Auftrags-Methoden (2011)
 *  - Rechnungs-Methoden (2012, großer Umbau 2015 für SEPA)
 *  - Liefer-Methoden (2013)
 *
 * TODO: Diese Klasse aufteilen in CustomerService, OrderService, InvoiceService, ShipmentService
 *       (NW-298, seit 2014 offen, Priorität immer wieder verschoben)
 *
 * Fehler-Strategie ist inkonsistent:
 *  - Ältere Methoden (2009-2011): return false bei Fehler
 *  - Neuere Methoden (2012+): Exception werfen
 *  - Manche Methoden (2013-2014): beides, je nach Fehlertyp
 *  - Codereviews haben das mehrfach bemängelt, keiner hat es gefixt
 */
class Northwind {

    /**
     * @var resource  MySQL-Connection
     * Manchmal wird stattdessen $GLOBALS['db'] verwendet wenn man
     * vergessen hat die Instanz-Variable zu benutzen. Beides funktioniert
     * (meistens) weil es dieselbe Connection ist.
     */
    private $db;

    /**
     * Items pro Seite für Paginierung.
     * Sollte ITEMS_PER_PAGE aus config.php verwenden aber war hier hardcoded
     * bevor die Konstante existierte. Jetzt gibt es beides.
     */
    private $perPage = 25;

    public function __construct($db = null) {
        if ($db === null) {
            // Fallback auf globale Variable -- passiert öfter als es sollte
            $this->db = $GLOBALS['db'];
        } else {
            $this->db = $db;
        }
    }

    // =========================================================================
    // KUNDEN-METHODEN
    // =========================================================================

    /**
     * Einzelnen Kunden laden.
     *
     * @param int $id
     * @return array|null
     */
    public function getKunde($id) {
        $id = (int)$id;
        $sql = "SELECT * FROM kunden WHERE id = " . $id . " LIMIT 1";
        $result = mysql_query($sql, $this->db);
        if (!$result) {
            return null;
        }
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row ? $row : null;
    }

    /**
     * Kundenliste mit Filter und Paginierung.
     *
     * @param array $filter  Mögliche Keys: suche, typ, aktiv, plz
     * @param int   $seite   1-basiert
     * @return array  ['kunden' => [...], 'gesamt' => int, 'seiten' => int]
     */
    public function getKundenListe($filter = array(), $seite = 1) {
        $where = array('1=1');

        // Filter zusammenbauen durch String-Konkatenation
        // TODO: Prepared Statements (NW-412, seit 2015 offen)
        if (!empty($filter['suche'])) {
            $s = mysql_real_escape_string($filter['suche'], $this->db);
            $where[] = "(name LIKE '%" . $s . "%' OR firma LIKE '%" . $s . "%' OR nummer LIKE '%" . $s . "%' OR email LIKE '%" . $s . "%')";
        }
        if (isset($filter['typ']) && in_array($filter['typ'], array('privat', 'geschaeft'))) {
            $where[] = "typ = '" . $filter['typ'] . "'";
        }
        if (isset($filter['aktiv'])) {
            $where[] = "aktiv = " . ($filter['aktiv'] ? 1 : 0);
        } else {
            // Standardmäßig nur aktive Kunden
            $where[] = "aktiv = 1";
        }
        if (!empty($filter['plz'])) {
            $plz = mysql_real_escape_string($filter['plz'], $this->db);
            $where[] = "plz = '" . $plz . "'";
        }

        $where_sql = implode(' AND ', $where);

        // Gesamt-Count für Paginierung
        $count_result = mysql_query("SELECT COUNT(*) as cnt FROM kunden WHERE " . $where_sql, $this->db);
        $count_row = mysql_fetch_assoc($count_result);
        $gesamt = (int)$count_row['cnt'];
        mysql_free_result($count_result);

        $seiten = (int)ceil($gesamt / $this->perPage);
        $seite = max(1, min($seite, $seiten > 0 ? $seiten : 1));
        $offset = ($seite - 1) * $this->perPage;

        $sql = "SELECT * FROM kunden WHERE " . $where_sql
             . " ORDER BY name ASC"
             . " LIMIT " . $this->perPage . " OFFSET " . $offset;
        $result = mysql_query($sql, $this->db);
        $kunden = array();
        while ($row = mysql_fetch_assoc($result)) {
            $kunden[] = $row;
        }
        mysql_free_result($result);

        return array('kunden' => $kunden, 'gesamt' => $gesamt, 'seiten' => $seiten, 'seite' => $seite);
    }

    /**
     * Kunden-Volltext-Suche.
     *
     * @param string $suchbegriff
     * @return array
     */
    public function sucheKunden($suchbegriff) {
        $s = mysql_real_escape_string($suchbegriff, $this->db);
        $sql = "SELECT id, nummer, name, firma, ort, email, aktiv
                FROM kunden
                WHERE (name LIKE '%" . $s . "%'
                    OR firma LIKE '%" . $s . "%'
                    OR nummer LIKE '%" . $s . "%'
                    OR email LIKE '%" . $s . "%'
                    OR telefon LIKE '%" . $s . "%')
                ORDER BY name ASC
                LIMIT 50";
        $result = mysql_query($sql, $this->db);
        $rows = array();
        while ($row = mysql_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysql_free_result($result);
        return $rows;
    }

    /**
     * Kunden anlegen oder aktualisieren.
     *
     * Ruft validiereKundendaten() auf -- die macht sowohl Regex-Prüfung (alt)
     * als auch BAV-Prüfung (neu). Wenn Validierung fehlschlägt: false.
     *
     * @param array $data
     * @return int|false  Kunden-ID oder false
     */
    public function speichereKunde($data) {
        $fehler = $this->validiereKundendaten($data);
        if (!empty($fehler)) {
            // TODO: Exception werfen statt false zurückgeben (2018)
            return false;
        }

        $jetzt = date('Y-m-d H:i:s');

        // BAV-Ergebnis cachen
        $bav_ergebnis = null;
        $bav_geprueft = null;
        if (!empty($data['bankleitzahl']) && !empty($data['kontonummer'])) {
            $validator = new BankValidator();
            $bav_ergebnis = $validator->istGueltigesBankverbindung($data['bankleitzahl'], $data['kontonummer']) ? 1 : 0;
            $bav_geprueft = $jetzt;
        }

        $felder = array(
            'name'          => isset($data['name']) ? mysql_real_escape_string($data['name'], $this->db) : '',
            'firma'         => isset($data['firma']) ? mysql_real_escape_string($data['firma'], $this->db) : '',
            'strasse'       => isset($data['strasse']) ? mysql_real_escape_string($data['strasse'], $this->db) : '',
            'hausnummer'    => isset($data['hausnummer']) ? mysql_real_escape_string($data['hausnummer'], $this->db) : '',
            'plz'           => isset($data['plz']) ? mysql_real_escape_string($data['plz'], $this->db) : '',
            'ort'           => isset($data['ort']) ? mysql_real_escape_string($data['ort'], $this->db) : '',
            'land'          => isset($data['land']) ? mysql_real_escape_string($data['land'], $this->db) : 'DEU',
            'email'         => isset($data['email']) ? mysql_real_escape_string($data['email'], $this->db) : '',
            'telefon'       => isset($data['telefon']) ? mysql_real_escape_string($data['telefon'], $this->db) : '',
            'bankleitzahl'  => isset($data['bankleitzahl']) ? mysql_real_escape_string($data['bankleitzahl'], $this->db) : '',
            'kontonummer'   => isset($data['kontonummer']) ? mysql_real_escape_string($data['kontonummer'], $this->db) : '',
            'bic'           => isset($data['bic']) ? mysql_real_escape_string($data['bic'], $this->db) : '',
            'iban'          => isset($data['iban']) ? mysql_real_escape_string($data['iban'], $this->db) : '',
            'typ'           => isset($data['typ']) && in_array($data['typ'], array('privat','geschaeft')) ? $data['typ'] : 'privat',
        );

        if (isset($data['id']) && $data['id'] > 0) {
            // Update
            $id = (int)$data['id'];
            $set_parts = array();
            foreach ($felder as $k => $v) {
                $set_parts[] = "`" . $k . "` = '" . $v . "'";
            }
            $set_parts[] = "geaendert_am = '" . $jetzt . "'";
            if ($bav_ergebnis !== null) {
                $set_parts[] = "bav_ergebnis = " . $bav_ergebnis;
                $set_parts[] = "bav_geprueft_am = '" . $bav_geprueft . "'";
            }
            $sql = "UPDATE kunden SET " . implode(', ', $set_parts) . " WHERE id = " . $id;
            mysql_query($sql, $this->db);
            return $id;
        } else {
            // Insert -- Kundennummer generieren
            $nummer = $this->_generiereKundennummer();
            $sql = "INSERT INTO kunden (nummer, name, firma, strasse, hausnummer, plz, ort, land, email, telefon,
                        bankleitzahl, kontonummer, bic, iban, typ, aktiv, angelegt_am, bav_ergebnis, bav_geprueft_am)
                    VALUES (
                        '" . $nummer . "',
                        '" . $felder['name'] . "',
                        '" . $felder['firma'] . "',
                        '" . $felder['strasse'] . "',
                        '" . $felder['hausnummer'] . "',
                        '" . $felder['plz'] . "',
                        '" . $felder['ort'] . "',
                        '" . $felder['land'] . "',
                        '" . $felder['email'] . "',
                        '" . $felder['telefon'] . "',
                        '" . $felder['bankleitzahl'] . "',
                        '" . $felder['kontonummer'] . "',
                        '" . $felder['bic'] . "',
                        '" . $felder['iban'] . "',
                        '" . $felder['typ'] . "',
                        1,
                        '" . $jetzt . "',
                        " . ($bav_ergebnis !== null ? $bav_ergebnis : 'NULL') . ",
                        " . ($bav_geprueft !== null ? "'" . $bav_geprueft . "'" : 'NULL') . "
                    )";
            mysql_query($sql, $this->db);
            return mysql_insert_id($this->db);
        }
    }

    /**
     * Kunden löschen.
     *
     * Inkonsistentes Verhalten: Hat der Kunde keine Aufträge → Hard Delete.
     * Hat er Aufträge → Soft Delete (aktiv = 0).
     * Das ist nirgendwo dokumentiert und war ursprünglich anders geplant.
     * Jira NW-445: "loescheKunde macht manchmal Hard Delete" -- offen seit 2016.
     *
     * @param int $id
     * @return bool
     */
    public function loescheKunde($id) {
        $id = (int)$id;

        // Prüfen ob Aufträge vorhanden
        $res = mysql_query("SELECT COUNT(*) as cnt FROM auftraege WHERE kunden_id = " . $id, $this->db);
        $row = mysql_fetch_assoc($res);
        $hat_auftraege = ($row['cnt'] > 0);
        mysql_free_result($res);

        if ($hat_auftraege) {
            // Soft Delete
            mysql_query("UPDATE kunden SET aktiv = 0, geaendert_am = NOW() WHERE id = " . $id, $this->db);
        } else {
            // Hard Delete -- kein Zurück
            mysql_query("DELETE FROM kunden WHERE id = " . $id, $this->db);
        }

        return true;
    }

    /**
     * Kundendaten validieren.
     *
     * Ruft BEIDE Validierungs-Mechanismen auf:
     * 1. validateBankleitzahl() aus helper.php (alt, 2009, nur Format)
     * 2. BankValidator::istGueltigesBankverbindung() (neu, 2014, BAV-Prüfsumme)
     *
     * Das ist redundant aber wir wollen sichergehen dass beide grünes Licht geben.
     * Die alte Funktion ist schneller (kein Datei-IO), die neue ist korrekt.
     *
     * @param array $data
     * @return array  Fehler-Array, leer wenn valide
     */
    public function validiereKundendaten($data) {
        $fehler = array();

        if (empty($data['name'])) {
            $fehler[] = 'Name ist erforderlich';
        }
        if (empty($data['strasse'])) {
            $fehler[] = 'Straße ist erforderlich';
        }
        if (empty($data['plz'])) {
            $fehler[] = 'PLZ ist erforderlich';
        } elseif (!preg_match('/^\d{5}$/', $data['plz'])) {
            $fehler[] = 'PLZ muss 5-stellig numerisch sein';
        }
        if (empty($data['ort'])) {
            $fehler[] = 'Ort ist erforderlich';
        }
        if (!empty($data['email']) && !validateEmail($data['email'])) {
            $fehler[] = 'E-Mail-Adresse ist ungültig';
        }

        // Bankverbindung validieren wenn angegeben
        if (!empty($data['bankleitzahl']) || !empty($data['kontonummer'])) {
            // Schritt 1: Format-Prüfung (alt, aus helper.php von 2009)
            if (!empty($data['bankleitzahl']) && !validateBankleitzahl($data['bankleitzahl'])) {
                $fehler[] = 'BLZ hat ungültiges Format (muss 8-stellig numerisch sein)';
            }

            // Schritt 2: BAV-Prüfsummen-Validierung (neu, 2014)
            // Nur ausführen wenn Format okay und beide Felder ausgefüllt
            if (empty($fehler) && !empty($data['bankleitzahl']) && !empty($data['kontonummer'])) {
                $validator = new BankValidator();
                if (!$validator->istGueltigesBankverbindung($data['bankleitzahl'], $data['kontonummer'])) {
                    // Achtung: Das ist eine Warnung, kein harter Fehler.
                    // Manche Banken sind in der BAV-Datenbank nicht drin.
                    // Deshalb speichern wir das Ergebnis (bav_ergebnis) aber
                    // blockieren nicht das Speichern.
                    // ... eigentlich. Hier wird es doch als Fehler behandelt. (NW-612)
                    $fehler[] = 'Bankverbindung konnte nicht verifiziert werden (BLZ/Kto-Prüfsumme ungültig)';
                }
            }

            if (!empty($data['bic']) && !empty($data['bankleitzahl'])) {
                $validator = new BankValidator();
                if (!$validator->istGueltigerBIC($data['bic'])) {
                    $fehler[] = 'BIC ist ungültig';
                }
            }
        }

        return $fehler;
    }

    /**
     * Interne Hilfsmethode: Kundennummer generieren.
     * Format: KD-YYYY-NNNNN
     *
     * @return string
     */
    private function _generiereKundennummer() {
        $jahr = date('Y');
        $res = mysql_query(
            "SELECT COUNT(*) as cnt FROM kunden WHERE nummer LIKE 'KD-" . $jahr . "-%'",
            $this->db
        );
        $row = mysql_fetch_assoc($res);
        $naechste = (int)$row['cnt'] + 1;
        mysql_free_result($res);
        return 'KD-' . $jahr . '-' . str_pad($naechste, 5, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // ARTIKEL-METHODEN
    // =========================================================================

    /**
     * Einzelnen Artikel laden.
     *
     * @param int $id
     * @return array|null
     */
    public function getArtikel($id) {
        $id = (int)$id;
        $result = mysql_query("SELECT * FROM artikel WHERE id = " . $id . " LIMIT 1", $this->db);
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row ? $row : null;
    }

    /**
     * Artikelliste mit Filter und Paginierung.
     *
     * @param array $filter
     * @param int   $seite
     * @return array
     */
    public function getArtikelListe($filter = array(), $seite = 1) {
        $where = array('aktiv = 1');

        if (!empty($filter['suche'])) {
            $s = mysql_real_escape_string($filter['suche'], $this->db);
            $where[] = "(name LIKE '%" . $s . "%' OR sku LIKE '%" . $s . "%' OR beschreibung LIKE '%" . $s . "%')";
        }
        if (isset($filter['mit_bestand']) && $filter['mit_bestand']) {
            $where[] = "lagerbestand > 0";
        }

        $where_sql = implode(' AND ', $where);
        $count_res = mysql_query("SELECT COUNT(*) as cnt FROM artikel WHERE " . $where_sql, $this->db);
        $count_row = mysql_fetch_assoc($count_res);
        $gesamt = (int)$count_row['cnt'];
        mysql_free_result($count_res);

        $seiten = (int)ceil($gesamt / $this->perPage);
        $seite = max(1, $seite);
        $offset = ($seite - 1) * $this->perPage;

        $result = mysql_query(
            "SELECT * FROM artikel WHERE " . $where_sql . " ORDER BY name ASC LIMIT " . $this->perPage . " OFFSET " . $offset,
            $this->db
        );
        $artikel = array();
        while ($row = mysql_fetch_assoc($result)) {
            $artikel[] = $row;
        }
        mysql_free_result($result);

        return array('artikel' => $artikel, 'gesamt' => $gesamt, 'seiten' => $seiten, 'seite' => $seite);
    }

    /**
     * Artikel anlegen oder aktualisieren.
     *
     * @param array $data
     * @return int|false
     */
    public function speichereArtikel($data) {
        $jetzt = date('Y-m-d H:i:s');

        if (isset($data['id']) && $data['id'] > 0) {
            $id = (int)$data['id'];
            $sql = "UPDATE artikel SET
                        sku           = '" . mysql_real_escape_string($data['sku'], $this->db) . "',
                        name          = '" . mysql_real_escape_string($data['name'], $this->db) . "',
                        beschreibung  = '" . mysql_real_escape_string(isset($data['beschreibung']) ? $data['beschreibung'] : '', $this->db) . "',
                        gewicht_kg    = " . (isset($data['gewicht_kg']) && is_numeric($data['gewicht_kg']) ? (float)$data['gewicht_kg'] : 'NULL') . ",
                        preis_netto   = " . (float)$data['preis_netto'] . ",
                        mwst_satz     = " . (isset($data['mwst_satz']) ? (float)$data['mwst_satz'] : 19.00) . ",
                        lagerbestand  = " . (isset($data['lagerbestand']) ? (int)$data['lagerbestand'] : 0) . "
                    WHERE id = " . $id;
            mysql_query($sql, $this->db);
            return $id;
        } else {
            $sql = "INSERT INTO artikel (sku, name, beschreibung, gewicht_kg, preis_netto, mwst_satz, lagerbestand, aktiv, angelegt_am)
                    VALUES (
                        '" . mysql_real_escape_string($data['sku'], $this->db) . "',
                        '" . mysql_real_escape_string($data['name'], $this->db) . "',
                        '" . mysql_real_escape_string(isset($data['beschreibung']) ? $data['beschreibung'] : '', $this->db) . "',
                        " . (isset($data['gewicht_kg']) && is_numeric($data['gewicht_kg']) ? (float)$data['gewicht_kg'] : 'NULL') . ",
                        " . (float)$data['preis_netto'] . ",
                        " . (isset($data['mwst_satz']) ? (float)$data['mwst_satz'] : 19.00) . ",
                        " . (isset($data['lagerbestand']) ? (int)$data['lagerbestand'] : 0) . ",
                        1,
                        '" . $jetzt . "'
                    )";
            mysql_query($sql, $this->db);
            return mysql_insert_id($this->db);
        }
    }

    /**
     * Lagerbestand direkt anpassen (Delta, positiv oder negativ).
     *
     * Keine Prüfung ob Bestand negativ wird -- das ist bekannt (NW-301).
     * Keine Transaktion -- bei gleichzeitigen Aufrufen kann Bestand falsch sein.
     *
     * @param int $artikel_id
     * @param float $menge_delta  Positiv = einbuchen, Negativ = ausbuchen
     * @return bool
     */
    public function aktualisiereBestand($artikel_id, $menge_delta) {
        $artikel_id = (int)$artikel_id;
        $delta = (float)$menge_delta;
        $sql = "UPDATE artikel SET lagerbestand = lagerbestand + " . $delta . " WHERE id = " . $artikel_id;
        mysql_query($sql, $this->db);
        return mysql_affected_rows($this->db) > 0;
    }

    // =========================================================================
    // AUFTRAGS-METHODEN
    // =========================================================================

    /**
     * Einzelnen Auftrag laden (mit Kundendaten).
     *
     * @param int $id
     * @return array|null
     */
    public function getAuftrag($id) {
        $id = (int)$id;
        $sql = "SELECT a.*, k.name as kunde_name, k.firma as kunde_firma,
                       k.strasse as kunde_strasse, k.hausnummer as kunde_hausnummer,
                       k.plz as kunde_plz, k.ort as kunde_ort,
                       k.email as kunde_email, k.telefon as kunde_telefon,
                       k.bankleitzahl as kunde_blz, k.kontonummer as kunde_kto,
                       k.iban as kunde_iban, k.bic as kunde_bic
                FROM auftraege a
                JOIN kunden k ON a.kunden_id = k.id
                WHERE a.id = " . $id . "
                LIMIT 1";
        $result = mysql_query($sql, $this->db);
        if (!$result) { return null; }
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row ? $row : null;
    }

    /**
     * Positionen eines Auftrags laden.
     *
     * @param int $auftrag_id
     * @return array
     */
    public function getAuftragPositionen($auftrag_id) {
        $auftrag_id = (int)$auftrag_id;
        $sql = "SELECT ap.*, a.sku as artikel_sku, a.gewicht_kg as artikel_gewicht
                FROM auftrag_positionen ap
                LEFT JOIN artikel a ON ap.artikel_id = a.id
                WHERE ap.auftrag_id = " . $auftrag_id . "
                ORDER BY ap.position_nr ASC, ap.id ASC";
        $result = mysql_query($sql, $this->db);
        $positionen = array();
        while ($row = mysql_fetch_assoc($result)) {
            $positionen[] = $row;
        }
        mysql_free_result($result);
        return $positionen;
    }

    /**
     * Aufträge eines Kunden laden.
     *
     * @param int  $kunden_id
     * @param bool $nur_aktive  Wenn true: keine stornierten Aufträge
     * @return array
     */
    public function getAuftraegeFuerKunde($kunden_id, $nur_aktive = true) {
        $kunden_id = (int)$kunden_id;
        $where = "kunden_id = " . $kunden_id;
        if ($nur_aktive) {
            $where .= " AND status != 9";
        }
        $result = mysql_query(
            "SELECT * FROM auftraege WHERE " . $where . " ORDER BY angelegt_am DESC",
            $this->db
        );
        $auftraege = array();
        while ($row = mysql_fetch_assoc($result)) {
            $auftraege[] = $row;
        }
        mysql_free_result($result);
        return $auftraege;
    }

    /**
     * Neuen Auftrag erstellen.
     *
     * WARNUNG: Keine Transaktion! Wenn das INSERT der Positionen fehlschlägt,
     * bleibt ein leerer Auftrag in der DB. Das ist ein bekanntes Problem (NW-178).
     * "Passiert in der Praxis nicht" -- Peter, 2011.
     * Es ist seitdem zweimal passiert.
     *
     * @param int   $kunden_id
     * @param array $positionen  Array von ['artikel_id', 'bezeichnung', 'menge', 'einzelpreis_netto', 'mwst_satz']
     * @return int|false  Auftrag-ID oder false
     */
    public function erstelleAuftrag($kunden_id, $positionen) {
        $kunden_id = (int)$kunden_id;

        if (empty($positionen)) {
            return false;
        }

        // Auftrag-Datensatz anlegen
        $auftrag_nr = $this->_generiereAuftragNummer();
        $jetzt = date('Y-m-d H:i:s');
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        $sql = "INSERT INTO auftraege (auftrag_nr, kunden_id, status, prioritaet, angelegt_am, angelegt_von)
                VALUES ('" . $auftrag_nr . "', " . $kunden_id . ", 1, 'normal', '" . $jetzt . "', " . $user_id . ")";
        mysql_query($sql, $this->db);
        $auftrag_id = mysql_insert_id($this->db);

        if (!$auftrag_id) {
            return false;
        }

        // Positionen einfügen -- KEINE TRANSAKTION (see above)
        $pos_nr = 1;
        foreach ($positionen as $pos) {
            $artikel_id = isset($pos['artikel_id']) && $pos['artikel_id'] > 0 ? (int)$pos['artikel_id'] : 'NULL';
            $bezeichnung = mysql_real_escape_string($pos['bezeichnung'], $this->db);
            $menge = (float)$pos['menge'];
            $einzelpreis = (float)$pos['einzelpreis_netto'];
            $mwst = isset($pos['mwst_satz']) ? (float)$pos['mwst_satz'] : 19.00;
            $einheit = isset($pos['einheit']) ? mysql_real_escape_string($pos['einheit'], $this->db) : 'Stück';

            $pos_sql = "INSERT INTO auftrag_positionen
                            (auftrag_id, artikel_id, bezeichnung, menge, einheit, einzelpreis_netto, mwst_satz, position_nr)
                        VALUES (" . $auftrag_id . ", " . $artikel_id . ", '" . $bezeichnung . "', "
                               . $menge . ", '" . $einheit . "', " . $einzelpreis . ", " . $mwst . ", " . $pos_nr . ")";
            mysql_query($pos_sql, $this->db);
            $pos_nr++;
        }

        return $auftrag_id;
    }

    /**
     * Auftragsstatus aktualisieren.
     * Wird von Pages UND von Cron-Jobs aufgerufen.
     *
     * Status-Codes:
     *  1 = neu
     *  2 = bestätigt  (Trigger 4 dekrementiert Lagerbestand)
     *  3 = in_bearbeitung
     *  4 = versendet
     *  5 = abgeschlossen
     *  9 = storniert
     *  (6,7,8 reserviert, nie verwendet)
     *
     * @param int $auftrag_id
     * @param int $neuer_status
     * @return bool
     */
    public function aktualisiereAuftragStatus($auftrag_id, $neuer_status) {
        $auftrag_id = (int)$auftrag_id;
        $neuer_status = (int)$neuer_status;
        $jetzt = date('Y-m-d H:i:s');

        $extra_felder = '';
        if ($neuer_status == 2) {
            $extra_felder = ", bestaetigt_am = '" . $jetzt . "'";
        } elseif ($neuer_status == 5) {
            $extra_felder = ", abgeschlossen_am = '" . $jetzt . "'";
        }

        $sql = "UPDATE auftraege SET status = " . $neuer_status . $extra_felder . " WHERE id = " . $auftrag_id;
        mysql_query($sql, $this->db);
        return mysql_affected_rows($this->db) > 0;
    }

    /**
     * Auftrag stornieren.
     *
     * BUG: Lagerbestand wird NICHT zurückgebucht wenn Auftrag bestätigt war.
     * Das ist ein bekanntes Problem (NW-445, seit 2016).
     * Der Trigger tr_auftrag_bestaetigt_after_update dekrementiert bei Bestätigung,
     * aber es gibt keinen Trigger der bei Stornierung addiert.
     *
     * Erstellt Gutschrift nur wenn bereits eine Rechnung existiert.
     *
     * @param int $auftrag_id
     * @return bool
     */
    public function storniereAuftrag($auftrag_id) {
        $auftrag_id = (int)$auftrag_id;

        // Status auf storniert setzen
        $this->aktualisiereAuftragStatus($auftrag_id, 9);

        // FEHLT: Lagerbestand zurückbuchen (NW-445)
        // Das wäre hier die richtige Stelle:
        // $positionen = $this->getAuftragPositionen($auftrag_id);
        // foreach ($positionen as $pos) { $this->aktualisiereBestand($pos['artikel_id'], $pos['menge']); }

        // Rechnung stornieren wenn vorhanden
        $res = mysql_query(
            "SELECT id FROM rechnungen WHERE auftrag_id = " . $auftrag_id . " AND status != 'storniert' LIMIT 1",
            $this->db
        );
        $re = mysql_fetch_assoc($res);
        mysql_free_result($res);

        if ($re) {
            mysql_query(
                "UPDATE rechnungen SET status = 'storniert' WHERE id = " . (int)$re['id'],
                $this->db
            );
            // Gutschrift erstellen
            $this->_erstelleGutschrift($auftrag_id);
        }

        return true;
    }

    /**
     * Auftragssumme berechnen.
     * Re-queryt die DB -- benutzt keine gecachten Daten aus getAuftragPositionen().
     * Führt zu N+1 Queries in manchen Kontexten. TODO: optimieren.
     *
     * @param int $auftrag_id
     * @return array  ['netto' => float, 'mwst' => float, 'brutto' => float]
     */
    public function berechneAuftragSumme($auftrag_id) {
        $auftrag_id = (int)$auftrag_id;
        $result = mysql_query(
            "SELECT menge, einzelpreis_netto, mwst_satz FROM auftrag_positionen WHERE auftrag_id = " . $auftrag_id,
            $this->db
        );
        $netto = 0.0;
        $mwst  = 0.0;
        while ($row = mysql_fetch_assoc($result)) {
            $pos_netto = (float)$row['menge'] * (float)$row['einzelpreis_netto'];
            $pos_mwst  = $pos_netto * ((float)$row['mwst_satz'] / 100);
            $netto += $pos_netto;
            $mwst  += $pos_mwst;
        }
        mysql_free_result($result);
        return array(
            'netto'  => round($netto, 2),
            'mwst'   => round($mwst, 2),
            'brutto' => round($netto + $mwst, 2),
        );
    }

    /**
     * Gutschrift erstellen (PHP4-Style private mit _ Prefix).
     * Wird nur von storniereAuftrag() aufgerufen.
     *
     * @param int $auftrag_id
     */
    private function _erstelleGutschrift($auftrag_id) {
        $auftrag_id = (int)$auftrag_id;
        $auftrag = $this->getAuftrag($auftrag_id);
        if (!$auftrag) { return; }

        $summe = $this->berechneAuftragSumme($auftrag_id);
        $jetzt = date('Y-m-d H:i:s');

        // Gutschrift als negative Rechnung speichern
        // Das ist nicht ideal aber funktioniert für unsere Buchhaltung
        $sql = "INSERT INTO rechnungen (auftrag_id, kunden_id, betrag_netto, betrag_brutto, mwst_betrag, status, faellig_am, angelegt_am)
                VALUES (" . $auftrag_id . ", " . (int)$auftrag['kunden_id'] . ",
                        " . (-1 * $summe['netto']) . ",
                        " . (-1 * $summe['brutto']) . ",
                        " . (-1 * $summe['mwst']) . ",
                        'gutschrift',
                        '" . date('Y-m-d') . "',
                        '" . $jetzt . "')";
        mysql_query($sql, $this->db);
    }

    /**
     * Interne Hilfsmethode: Auftragsnummer generieren.
     *
     * @return string  z.B. AUF-2023-08421
     */
    private function _generiereAuftragNummer() {
        $jahr = date('Y');
        $res = mysql_query(
            "SELECT COUNT(*) as cnt FROM auftraege WHERE auftrag_nr LIKE 'AUF-" . $jahr . "-%'",
            $this->db
        );
        $row = mysql_fetch_assoc($res);
        $naechste = (int)$row['cnt'] + 1;
        mysql_free_result($res);
        return 'AUF-' . $jahr . '-' . str_pad($naechste, 5, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // RECHNUNGS-METHODEN
    // =========================================================================

    /**
     * Rechnungsliste laden.
     *
     * @param array $filter  Keys: status, kunden_id, von_datum, bis_datum
     * @return array
     */
    public function getRechnungen($filter = array()) {
        $where = array('1=1');

        if (!empty($filter['status'])) {
            $s = mysql_real_escape_string($filter['status'], $this->db);
            $where[] = "r.status = '" . $s . "'";
        }
        if (!empty($filter['kunden_id'])) {
            $where[] = "r.kunden_id = " . (int)$filter['kunden_id'];
        }
        if (!empty($filter['von_datum'])) {
            $d = mysql_real_escape_string($filter['von_datum'], $this->db);
            $where[] = "DATE(r.angelegt_am) >= '" . $d . "'";
        }
        if (!empty($filter['bis_datum'])) {
            $d = mysql_real_escape_string($filter['bis_datum'], $this->db);
            $where[] = "DATE(r.angelegt_am) <= '" . $d . "'";
        }

        $sql = "SELECT r.*, k.name as kunde_name, k.nummer as kunde_nummer
                FROM rechnungen r
                JOIN kunden k ON r.kunden_id = k.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.angelegt_am DESC";
        $result = mysql_query($sql, $this->db);
        $rechnungen = array();
        while ($row = mysql_fetch_assoc($result)) {
            $rechnungen[] = $row;
        }
        mysql_free_result($result);
        return $rechnungen;
    }

    /**
     * Einzelne Rechnung laden.
     *
     * @param int $id
     * @return array|null
     */
    public function getRechnung($id) {
        $id = (int)$id;
        $sql = "SELECT r.*, k.name as kunde_name, k.firma as kunde_firma,
                       k.strasse as kunde_strasse, k.hausnummer as kunde_hausnummer,
                       k.plz as kunde_plz, k.ort as kunde_ort,
                       k.email as kunde_email, k.bankleitzahl as kunde_blz,
                       k.kontonummer as kunde_kto, k.iban as kunde_iban
                FROM rechnungen r
                JOIN kunden k ON r.kunden_id = k.id
                WHERE r.id = " . $id . "
                LIMIT 1";
        $result = mysql_query($sql, $this->db);
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row ? $row : null;
    }

    /**
     * Rechnung für einen Auftrag erstellen.
     * Der Trigger tr_rechnung_nummer_before_insert setzt die Rechnungsnummer automatisch.
     *
     * @param int $auftrag_id
     * @return int|false  Rechnungs-ID oder false
     */
    public function erstelleRechnung($auftrag_id) {
        $auftrag_id = (int)$auftrag_id;
        $auftrag = $this->getAuftrag($auftrag_id);
        if (!$auftrag) { return false; }

        // Prüfen ob schon eine Rechnung existiert
        $res = mysql_query(
            "SELECT id FROM rechnungen WHERE auftrag_id = " . $auftrag_id . " AND status != 'storniert' LIMIT 1",
            $this->db
        );
        $existing = mysql_fetch_assoc($res);
        mysql_free_result($res);
        if ($existing) {
            // war mal eine Exception, jetzt false weil die Page damit umgehen kann
            return false;
        }

        $summe = $this->berechneAuftragSumme($auftrag_id);
        $faellig = date('Y-m-d', strtotime('+30 days'));
        $jetzt = date('Y-m-d H:i:s');

        // rechnungs_nr wird durch Trigger gesetzt, daher NULL hier
        $sql = "INSERT INTO rechnungen
                    (rechnungs_nr, auftrag_id, kunden_id, betrag_netto, betrag_brutto, mwst_betrag, status, faellig_am, angelegt_am)
                VALUES
                    (NULL, " . $auftrag_id . ", " . (int)$auftrag['kunden_id'] . ",
                     " . $summe['netto'] . ", " . $summe['brutto'] . ", " . $summe['mwst'] . ",
                     'offen', '" . $faellig . "', '" . $jetzt . "')";
        mysql_query($sql, $this->db);
        $rechnung_id = mysql_insert_id($this->db);

        if ($rechnung_id) {
            // Auftragsstatus aktualisieren wenn noch auf "bestätigt"
            if ($auftrag['status'] == 2) {
                $this->aktualisiereAuftragStatus($auftrag_id, 3);
            }
            return $rechnung_id;
        }
        return false;
    }

    /**
     * Zahlung verarbeiten.
     *
     * Das ist die komplexeste Methode in der ganzen Applikation.
     * Aufrufreihenfolge:
     *   rechnung_zahlung.php -> new BankValidator() -> BAV (2. Initialisierung)
     *   rechnung_zahlung.php -> new Northwind() -> verarbeiteZahlung()
     *                       -> new BAV() (3. Initialisierung, liest ConfigurationRegistry)
     *
     * @param int    $rechnung_id
     * @param string $blz
     * @param string $kto
     * @param float  $betrag
     * @return bool
     * @throws Exception  Wenn DB-Fehler (inkonsistent: manchmal false, manchmal Exception)
     */
    public function verarbeiteZahlung($rechnung_id, $blz, $kto, $betrag) {
        $rechnung_id = (int)$rechnung_id;
        $betrag = (float)str_replace(',', '.', $betrag);

        if ($betrag <= 0) {
            return false;
        }

        $rechnung = $this->getRechnung($rechnung_id);
        if (!$rechnung) {
            return false;
        }
        if ($rechnung['status'] == 'bezahlt' || $rechnung['status'] == 'storniert') {
            return false;
        }

        // BAV-Validierung -- nochmal, auch wenn rechnung_zahlung.php das schon gemacht hat.
        // Man weiß nie ob verarbeiteZahlung direkt aufgerufen wird (z.B. aus Tests oder Cron).
        // Deshalb: hier immer validieren, egal was der Aufrufer gemacht hat.
        //
        // ACHTUNG: Das initialisiert die BAV-Konfiguration ein weiteres Mal.
        // Sicher ist sicher.
        if (!file_exists(APP_ROOT . '/vendor/malkusch/bav/autoloader/autoloader.php') &&
            !file_exists(APP_ROOT . '/vendor/autoload.php')) {
            // BAV nicht verfügbar -- Validierung überspringen
            // TODO: Das sollte ein harter Fehler sein (NW-621)
            logError('verarbeiteZahlung: BAV nicht verfügbar, Validierung übersprungen');
        } else {
            $config = new \malkusch\bav\DefaultConfiguration();
            \malkusch\bav\ConfigurationRegistry::setConfiguration($config);
            $bav = new \malkusch\bav\BAV();

            try {
                if (!$bav->isValidBankAccount($blz, $kto)) {
                    return false;
                }
            } catch (Exception $e) {
                logError('verarbeiteZahlung BAV-Fehler: ' . $e->getMessage());
                // Im Zweifelsfall: weiter machen (Entscheidung von 2015, NW-498)
                // "Lieber eine Zahlung mit schlechter BLZ als keine Zahlung"
            }
        }

        $jetzt = date('Y-m-d H:i:s');
        $blz_safe = mysql_real_escape_string($blz, $this->db);
        $kto_safe = mysql_real_escape_string($kto, $this->db);

        // Zahlung eintragen
        $sql_zahlung = "INSERT INTO zahlungen
                            (rechnung_id, betrag, zahlungsart, blz, kontonummer, bav_validiert, bav_validiert_am, datum)
                        VALUES
                            (" . $rechnung_id . ", " . $betrag . ", 'lastschrift',
                             '" . $blz_safe . "', '" . $kto_safe . "',
                             1, '" . $jetzt . "', '" . $jetzt . "')";
        $res = mysql_query($sql_zahlung, $this->db);
        if (!$res) {
            // TODO: Exception werfen statt false zurückgeben (2018)
            logError('verarbeiteZahlung: INSERT zahlungen fehlgeschlagen: ' . mysql_error($this->db));
            return false;
        }

        // Rechnung auf bezahlt setzen.
        // ACHTUNG: Das löst Trigger tr_rechnung_bezahlt_after_update aus,
        // der den zugehörigen Auftrag auf Status 5 (abgeschlossen) setzt.
        // Das passiert unsichtbar -- wer den Auftragsstatus debuggt, findet
        // diesen Trigger nicht im PHP-Code.
        $sql_rechnung = "UPDATE rechnungen SET
                             status = 'bezahlt',
                             bezahlt_am = '" . $jetzt . "',
                             bezahlter_betrag = " . $betrag . ",
                             zahlung_blz = '" . $blz_safe . "',
                             zahlung_kto = '" . $kto_safe . "'
                         WHERE id = " . $rechnung_id;
        $res2 = mysql_query($sql_rechnung, $this->db);
        if (!$res2) {
            throw new Exception('Rechnungsstatus konnte nicht aktualisiert werden: ' . mysql_error($this->db));
        }

        // Zahlungsbestätigung senden
        $this->_sendeZahlungsbestaetigung($rechnung_id);

        return true;
    }

    /**
     * Zahlungsbestätigung per E-Mail senden.
     * Wird nur aus verarbeiteZahlung() aufgerufen.
     *
     * @param int $rechnung_id
     */
    private function _sendeZahlungsbestaetigung($rechnung_id) {
        $rechnung = $this->getRechnung($rechnung_id);
        if (!$rechnung || empty($rechnung['kunde_email'])) {
            return;
        }

        $betreff = 'Zahlungseingang bestätigt - Rechnung ' . $rechnung['rechnungs_nr'];
        $text  = "Sehr geehrte/r " . $rechnung['kunde_name'] . ",\n\n";
        $text .= "wir bestätigen den Eingang Ihrer Zahlung über ";
        $text .= number_format($rechnung['bezahlter_betrag'], 2, ',', '.') . " EUR ";
        $text .= "für Rechnung " . $rechnung['rechnungs_nr'] . ".\n\n";
        $text .= "Vielen Dank für Ihr Vertrauen.\n\n";
        $text .= "Mit freundlichen Grüßen\nNorthwind Logistics GmbH\nBuchhaltung";

        // mail() direkt -- kein Templating, kein Queue, kein Retry
        @mail(
            $rechnung['kunde_email'],
            $betreff,
            $text,
            "From: buchhaltung@northwind-logistics.de\r\nContent-Type: text/plain; charset=UTF-8"
        );
    }

    /**
     * Rechnung als HTML generieren und per E-Mail senden.
     * Erzeugt HTML per String-Konkatenation. Kein Template-System.
     *
     * @param int $rechnung_id
     * @return bool
     */
    public function sendeRechnung($rechnung_id) {
        $rechnung = $this->getRechnung($rechnung_id);
        if (!$rechnung) { return false; }

        $positionen = $this->getAuftragPositionen($rechnung['auftrag_id']);

        // "PDF" als HTML -- eigentlich wollten wir tcpdf verwenden aber das
        // war damals zu schwierig zu installieren. HTML per Mail ist "gut genug".
        $html  = '<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:4px;}</style></head><body>';
        $html .= '<h2>Rechnung ' . htmlspecialchars($rechnung['rechnungs_nr']) . '</h2>';
        $html .= '<p><strong>Rechnungsdatum:</strong> ' . date('d.m.Y', strtotime($rechnung['angelegt_am'])) . '<br>';
        $html .= '<strong>Fällig am:</strong> ' . date('d.m.Y', strtotime($rechnung['faellig_am'])) . '</p>';
        $html .= '<p>' . htmlspecialchars($rechnung['kunde_name']) . '<br>';
        $html .= htmlspecialchars($rechnung['kunde_strasse']) . ' ' . htmlspecialchars($rechnung['kunde_hausnummer'] ?? '') . '<br>';
        $html .= htmlspecialchars($rechnung['kunde_plz']) . ' ' . htmlspecialchars($rechnung['kunde_ort']) . '</p>';
        $html .= '<table><tr><th>Pos</th><th>Bezeichnung</th><th>Menge</th><th>Einzelpreis</th><th>MwSt</th><th>Gesamt</th></tr>';

        $pos_nr = 1;
        foreach ($positionen as $pos) {
            $gesamt = (float)$pos['menge'] * (float)$pos['einzelpreis_netto'];
            $html .= '<tr><td>' . $pos_nr . '</td>';
            $html .= '<td>' . htmlspecialchars($pos['bezeichnung']) . '</td>';
            $html .= '<td>' . number_format((float)$pos['menge'], 2, ',', '.') . ' ' . htmlspecialchars($pos['einheit']) . '</td>';
            $html .= '<td>' . number_format((float)$pos['einzelpreis_netto'], 2, ',', '.') . ' EUR</td>';
            $html .= '<td>' . $pos['mwst_satz'] . '%</td>';
            $html .= '<td>' . number_format($gesamt, 2, ',', '.') . ' EUR</td></tr>';
            $pos_nr++;
        }

        $html .= '</table>';
        $html .= '<p><strong>Netto: ' . number_format((float)$rechnung['betrag_netto'], 2, ',', '.') . ' EUR</strong><br>';
        $html .= 'MwSt: ' . number_format((float)$rechnung['mwst_betrag'], 2, ',', '.') . ' EUR<br>';
        $html .= '<strong>Brutto: ' . number_format((float)$rechnung['betrag_brutto'], 2, ',', '.') . ' EUR</strong></p>';
        $html .= '</body></html>';

        if (empty($rechnung['kunde_email'])) {
            return false;
        }

        $headers  = "From: buchhaltung@northwind-logistics.de\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return @mail(
            $rechnung['kunde_email'],
            'Ihre Rechnung ' . $rechnung['rechnungs_nr'] . ' von Northwind Logistics',
            $html,
            $headers
        );
    }

    // =========================================================================
    // LIEFER-METHODEN
    // =========================================================================

    /**
     * Einzelne Lieferung laden.
     *
     * @param int $id
     * @return array|null
     */
    public function getLieferung($id) {
        $id = (int)$id;
        $sql = "SELECT l.*, a.auftrag_nr, a.kunden_id,
                       k.name as kunde_name
                FROM lieferungen l
                JOIN auftraege a ON l.auftrag_id = a.id
                JOIN kunden k ON a.kunden_id = k.id
                WHERE l.id = " . $id . "
                LIMIT 1";
        $result = mysql_query($sql, $this->db);
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row ? $row : null;
    }

    /**
     * Alle Lieferungen für einen Auftrag.
     *
     * @param int $auftrag_id
     * @return array
     */
    public function getLieferungenFuerAuftrag($auftrag_id) {
        $auftrag_id = (int)$auftrag_id;
        $result = mysql_query(
            "SELECT * FROM lieferungen WHERE auftrag_id = " . $auftrag_id . " ORDER BY angelegt_am DESC",
            $this->db
        );
        $lieferungen = array();
        while ($row = mysql_fetch_assoc($result)) {
            $lieferungen[] = $row;
        }
        mysql_free_result($result);
        return $lieferungen;
    }

    /**
     * Neue Lieferung erstellen.
     * Tracking-URL wird durch String-Konkatenation aus carrier_code gebaut.
     * Der INSERT löst Trigger tr_lieferung_erstellt_after_insert aus.
     *
     * @param int    $auftrag_id
     * @param string $carrier_code  DHL, DPD, UPS, GLS
     * @return int|false
     */
    public function erstelleLieferung($auftrag_id, $carrier_code) {
        $auftrag_id = (int)$auftrag_id;
        $carrier_code = mysql_real_escape_string(strtoupper($carrier_code), $this->db);
        $jetzt = date('Y-m-d H:i:s');
        $heute = date('Y-m-d');

        // Tracking-URL-Templates -- hardcoded, sollte eigentlich in DB-Tabelle stehen
        $tracking_templates = array(
            'DHL' => 'https://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc={TRACKING}',
            'DPD' => 'https://tracking.dpd.de/status/de_DE/parcel/{TRACKING}',
            'UPS' => 'https://www.ups.com/track?loc=de_DE&tracknum={TRACKING}',
            'GLS' => 'https://gls-group.eu/DE/de/paketverfolgung?match={TRACKING}',
        );

        // Tracking-Nummer generieren -- einfache Simulation
        $tracking_nr = strtoupper($carrier_code) . '-' . date('Ymd') . '-' . str_pad($auftrag_id, 6, '0', STR_PAD_LEFT);
        $tracking_url = '';
        if (isset($tracking_templates[$carrier_code])) {
            $tracking_url = str_replace('{TRACKING}', $tracking_nr, $tracking_templates[$carrier_code]);
        }

        $sql = "INSERT INTO lieferungen
                    (auftrag_id, status, carrier_code, tracking_nummer, tracking_url, versanddatum, angelegt_am)
                VALUES
                    (" . $auftrag_id . ", 'erstellt', '" . $carrier_code . "',
                     '" . mysql_real_escape_string($tracking_nr, $this->db) . "',
                     '" . mysql_real_escape_string($tracking_url, $this->db) . "',
                     '" . $heute . "', '" . $jetzt . "')";
        mysql_query($sql, $this->db);
        $id = mysql_insert_id($this->db);
        return $id ? $id : false;
    }

    /**
     * Lieferstatus aktualisieren.
     * Wird von Cron-Job update_lieferstatus.php aufgerufen.
     * Bei Status 'zugestellt' feuert Trigger tr_lieferung_zugestellt_after_update
     * der auftraege.status auf 4 setzt (nicht 5 -- das ist ein Bug im Trigger).
     *
     * @param int    $lieferung_id
     * @param string $status  erstellt|abgeholt|unterwegs|zugestellt|zurueck
     * @return bool
     */
    public function aktualisiereLieferstatus($lieferung_id, $status) {
        $lieferung_id = (int)$lieferung_id;
        $status = mysql_real_escape_string($status, $this->db);

        $extra = '';
        if ($status == 'zugestellt') {
            $extra = ", zustelldatum_ist = CURDATE()";
        }

        $sql = "UPDATE lieferungen SET status = '" . $status . "'" . $extra . " WHERE id = " . $lieferung_id;
        mysql_query($sql, $this->db);
        return mysql_affected_rows($this->db) > 0;
    }
}
