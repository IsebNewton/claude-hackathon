-- ============================================================
-- Northwind Logistics GmbH -- Datenbankschema
-- ============================================================
-- Erstellt: 2009 von Klaus (nicht mehr bei uns)
-- Letzte größere Änderung: 2019 (Peter hat die banken_cache Tabelle "aufgeräumt")
-- Stand: irgendwann 2022 (niemand pflegt diesen Kommentar)
--
-- ACHTUNG: Trigger enthalten Geschäftslogik! Vor Änderungen an
-- den Status-Workflows bitte ALLE Trigger lesen. Die meisten Bugs
-- mit Auftragsstatus haben hier ihren Ursprung.
-- ============================================================

SET NAMES utf8;
SET foreign_key_checks = 0;

-- ============================================================
-- Tabelle: kunden
-- ============================================================
-- 2009: Grundstruktur angelegt
-- 2013: SEPA-Felder hinzugefügt (bankleitzahl, kontonummer, bic, iban)
--       wegen EU-Umstellung. IBAN wird berechnet, nicht eingegeben.
-- 2017: created-Spalte hinzugefügt (doppelt zu angelegt_am, aber
--       jemand brauchte einen Timestamp für einen Bericht)
-- 2020: bav_geprueft_am, bav_ergebnis hinzugefügt um BAV-Aufrufe zu cachen
-- ============================================================
CREATE TABLE IF NOT EXISTS kunden (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    nummer          VARCHAR(20) NOT NULL COMMENT 'Kundennummer z.B. KD-2009-001',
    name            VARCHAR(200) NOT NULL,
    firma           VARCHAR(200) DEFAULT NULL COMMENT 'Firmenname, NULL bei Privatpersonen',
    strasse         VARCHAR(200) NOT NULL,
    hausnummer      VARCHAR(10) NOT NULL,
    plz             VARCHAR(5) NOT NULL,
    ort             VARCHAR(100) NOT NULL,
    land            VARCHAR(3) DEFAULT 'DEU' COMMENT 'ISO 3166-1 alpha-3, fast immer DEU',
    email           VARCHAR(255) DEFAULT NULL,
    telefon         VARCHAR(50) DEFAULT NULL,
    -- SEPA-Bankdaten (hinzugefügt 2013 für Lastschriftverfahren)
    bankleitzahl    VARCHAR(8) DEFAULT NULL COMMENT '8-stellige Bankleitzahl (BLZ)',
    kontonummer     VARCHAR(10) DEFAULT NULL COMMENT 'Kontonummer, bis 10-stellig',
    bic             VARCHAR(11) DEFAULT NULL COMMENT 'SWIFT/BIC Code',
    iban            VARCHAR(34) DEFAULT NULL COMMENT 'IBAN (wird von BankValidator berechnet)',
    -- Kundentyp und Status
    typ             ENUM('privat','geschaeft') DEFAULT 'privat',
    aktiv           TINYINT(1) DEFAULT 1,
    -- Zeitstempel (inkonsistent benannt - angelegt_am und created koexistieren)
    angelegt_am     DATETIME NOT NULL,
    created         TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Wurde 2017 hinzugefuegt, doppelt zu angelegt_am',
    geaendert_am    DATETIME DEFAULT NULL,
    angelegt_von    INT(11) DEFAULT NULL COMMENT 'User-ID, kein FK weil Benutzer manchmal geloescht werden',
    -- BAV-Validierungscache (hinzugefügt 2020 weil BAV "zu langsam" war)
    bav_geprueft_am DATETIME DEFAULT NULL,
    bav_ergebnis    TINYINT(1) DEFAULT NULL COMMENT '1=gueltig, 0=ungueltig, NULL=nicht geprueft',
    PRIMARY KEY (id),
    UNIQUE KEY idx_nummer (nummer),
    KEY idx_plz (plz),
    KEY idx_blz (bankleitzahl),
    KEY idx_aktiv (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Kundenstammdaten';

-- ============================================================
-- Tabelle: artikel
-- ============================================================
-- 2009: Grundstruktur angelegt
-- 2011: Dimensionen hinzugefügt (laenge_cm, breite_cm, hoehe_cm)
--       wegen Speditionskosten-Berechnung - wird aber nie benutzt
-- 2015: mwst_satz pro Artikel (vorher war 19% hardcoded überall)
-- ============================================================
CREATE TABLE IF NOT EXISTS artikel (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    sku             VARCHAR(50) NOT NULL COMMENT 'Artikelnummer/Stock Keeping Unit',
    name            VARCHAR(255) NOT NULL,
    beschreibung    TEXT DEFAULT NULL,
    -- Maße und Gewicht (für Spedition, wird selten gepflegt)
    gewicht_kg      DECIMAL(8,3) DEFAULT NULL,
    laenge_cm       DECIMAL(8,1) DEFAULT NULL,
    breite_cm       DECIMAL(8,1) DEFAULT NULL,
    hoehe_cm        DECIMAL(8,1) DEFAULT NULL,
    -- Preise
    preis_netto     DECIMAL(10,2) NOT NULL,
    mwst_satz       DECIMAL(4,2) DEFAULT 19.00 COMMENT 'MwSt-Satz in Prozent',
    -- Lager
    lagerbestand    INT(11) DEFAULT 0 COMMENT 'Kann negativ werden! Kein Check-Constraint vorhanden.',
    aktiv           TINYINT(1) DEFAULT 1 COMMENT 'Soft-Delete Flag',
    angelegt_am     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_sku (sku),
    KEY idx_aktiv (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Artikelstammdaten';

-- ============================================================
-- Tabelle: auftraege
-- ============================================================
-- 2009: Grundstruktur
-- 2012: Lieferanschrift-Felder hinzugefügt (war vorher immer Kundenadresse)
-- 2014: prioritaet hinzugefügt (Express-Aufträge)
-- 2018: bestaetigt_am, abgeschlossen_am für Reporting
--
-- STATUS-CODES (WICHTIG - nirgendwo sonst dokumentiert):
--   1 = Neu (angelegt, noch nicht bestätigt)
--   2 = Bestätigt (Lagerbestand wird durch Trigger 4 reserviert!)
--   3 = In Bearbeitung (wird durch Trigger 2 gesetzt wenn Lieferung angelegt)
--   4 = Versendet (wird durch Trigger 3 gesetzt wenn Lieferung zugestellt)
--   5 = Abgeschlossen (wird durch Trigger 1 gesetzt wenn Rechnung bezahlt!)
--   6, 7, 8 = RESERVIERT - nie benutzt worden
--   9 = Storniert
--
-- ACHTUNG: Status 5 wird NICHT von der App direkt gesetzt!
-- Er wird ausschließlich durch den Datenbank-Trigger tr_rechnung_bezahlt gesetzt.
-- Wer das nicht weiß, sucht ewig warum Aufträge nie auf 5 gehen.
-- ============================================================
CREATE TABLE IF NOT EXISTS auftraege (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    auftrag_nr      VARCHAR(20) NOT NULL COMMENT 'z.B. AUF-2023-08421',
    kunden_id       INT(11) NOT NULL,
    status          INT(2) DEFAULT 1 COMMENT '1=neu,2=bestaetigt,3=in_bearbeitung,4=versendet,5=abgeschlossen,9=storniert',
    prioritaet      ENUM('normal','hoch','express') DEFAULT 'normal',
    lieferdatum_soll DATE DEFAULT NULL,
    lieferdatum_ist  DATE DEFAULT NULL COMMENT 'Wird durch Trigger 3 gesetzt',
    -- Abweichende Lieferanschrift (hinzugefügt 2012)
    lieferanschrift_strasse VARCHAR(200) DEFAULT NULL,
    lieferanschrift_plz     VARCHAR(5) DEFAULT NULL,
    lieferanschrift_ort     VARCHAR(100) DEFAULT NULL,
    bemerkungen     TEXT DEFAULT NULL,
    angelegt_am     DATETIME NOT NULL,
    bestaetigt_am   DATETIME DEFAULT NULL,
    abgeschlossen_am DATETIME DEFAULT NULL COMMENT 'Wird durch Trigger 1 gesetzt',
    angelegt_von    INT(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_auftrag_nr (auftrag_nr),
    KEY idx_kunden_id (kunden_id),
    KEY idx_status (status),
    KEY idx_angelegt_am (angelegt_am),
    CONSTRAINT fk_auftraege_kunden FOREIGN KEY (kunden_id) REFERENCES kunden (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Auftragskoepfe';

-- ============================================================
-- Tabelle: auftrag_positionen
-- ============================================================
-- 2009: Grundstruktur
-- 2011: artikel_id darf NULL sein für Textpositionen (Frachtzuschlag etc.)
-- 2016: position_nr hinzugefügt wegen Sortierung im PDF
-- ============================================================
CREATE TABLE IF NOT EXISTS auftrag_positionen (
    id                  INT(11) NOT NULL AUTO_INCREMENT,
    auftrag_id          INT(11) NOT NULL,
    artikel_id          INT(11) DEFAULT NULL COMMENT 'NULL erlaubt für manuelle Textpositionen',
    bezeichnung         VARCHAR(255) NOT NULL COMMENT 'Kopie des Artikelnamens zum Bestellzeitpunkt',
    menge               DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    einheit             VARCHAR(20) DEFAULT 'Stück',
    einzelpreis_netto   DECIMAL(10,2) NOT NULL COMMENT 'Preis zum Bestellzeitpunkt, nicht aktueller Artikelpreis',
    mwst_satz           DECIMAL(4,2) DEFAULT 19.00,
    position_nr         INT(3) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_auftrag_id (auftrag_id),
    KEY idx_artikel_id (artikel_id),
    CONSTRAINT fk_positionen_auftraege FOREIGN KEY (auftrag_id) REFERENCES auftraege (id),
    CONSTRAINT fk_positionen_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Auftragspositionen/Zeilenelemente';

-- ============================================================
-- Tabelle: lieferungen
-- ============================================================
-- 2009: Grundstruktur (war anfangs nur carrier_code und tracking_nummer)
-- 2011: Empfängeradresse hinzugefügt (war vorher über Auftrag gejoint)
--       PROBLEM: Jetzt gibt es 3 Adressen pro Auftrag:
--       kunden.strasse, auftraege.lieferanschrift_*, lieferungen.empfaenger_*
--       Welche ist die "richtige"? Kommt drauf an welcher Entwickler gefragt wird.
-- 2015: tracking_url hinzugefügt - wird als String aus carrier_code zusammengebaut,
--       ist aber manchmal veraltet wenn sich die Carrier-URLs ändern
-- 2019: zustelldatum_soll/ist hinzugefügt für SLA-Reporting
--
-- STATUS: 'erstellt', 'abgeholt', 'unterwegs', 'zugestellt', 'zurueck'
-- Beachte: Status ist hier VARCHAR, bei auftraege ist es INT. Inkonsistenz gewollt? Nein.
-- ============================================================
CREATE TABLE IF NOT EXISTS lieferungen (
    id                  INT(11) NOT NULL AUTO_INCREMENT,
    auftrag_id          INT(11) NOT NULL,
    status              VARCHAR(30) DEFAULT 'erstellt' COMMENT 'erstellt/abgeholt/unterwegs/zugestellt/zurueck',
    carrier_code        VARCHAR(10) DEFAULT NULL COMMENT 'DHL, DPD, UPS, GLS, HERMES, SELBST',
    tracking_nummer     VARCHAR(100) DEFAULT NULL,
    tracking_url        VARCHAR(500) DEFAULT NULL COMMENT 'Wird aus carrier_code + tracking_nummer zusammengebaut, kann veraltet sein',
    gewicht_kg          DECIMAL(8,3) DEFAULT NULL,
    versanddatum        DATE DEFAULT NULL,
    zustelldatum_ist    DATE DEFAULT NULL,
    zustelldatum_soll   DATE DEFAULT NULL,
    -- Empfängeradresse (3. Kopie der Adresse im System, jede kann abweichen)
    empfaenger_name     VARCHAR(200) DEFAULT NULL,
    empfaenger_strasse  VARCHAR(200) DEFAULT NULL,
    empfaenger_plz      VARCHAR(5) DEFAULT NULL,
    empfaenger_ort      VARCHAR(100) DEFAULT NULL,
    angelegt_am         DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_auftrag_id (auftrag_id),
    KEY idx_status (status),
    KEY idx_carrier_tracking (carrier_code, tracking_nummer),
    CONSTRAINT fk_lieferungen_auftraege FOREIGN KEY (auftrag_id) REFERENCES auftraege (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Lieferungen/Versendungen zu Auftraegen';

-- ============================================================
-- Tabelle: rechnungen
-- ============================================================
-- 2009: Grundstruktur
-- 2013: SEPA-Felder (zahlung_blz, zahlung_kto, zahlung_iban)
-- 2016: Mahnwesen (mahnstufe, letzte_mahnung, mahngebuehr)
-- 2018: kunden_id direkt hinzugefügt (eigentlich über auftrag_id erreichbar,
--       aber Reporting-Abfragen waren "zu komplex")
--
-- WICHTIG: rechnungs_nr wird durch Trigger tr_rechnung_nummer gesetzt!
-- Beim INSERT muss rechnungs_nr leer gelassen werden (oder ein Placeholder).
-- Die Funktion getNextRechnungsNr() in helper.php macht das FALSCH und
-- führt zu Konflikten. Beide Mechanismen koexistieren und keiner weiß welcher greift.
-- ============================================================
CREATE TABLE IF NOT EXISTS rechnungen (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    rechnungs_nr    VARCHAR(20) DEFAULT NULL COMMENT 'Wird durch Trigger tr_rechnung_nummer gesetzt!',
    auftrag_id      INT(11) NOT NULL,
    kunden_id       INT(11) NOT NULL COMMENT 'Redundant (via auftrag_id erreichbar), aber praktisch fuer Reports',
    betrag_netto    DECIMAL(10,2) NOT NULL,
    betrag_brutto   DECIMAL(10,2) NOT NULL,
    mwst_betrag     DECIMAL(10,2) NOT NULL,
    -- Status: offen, teilbezahlt, bezahlt, storniert, gemahnt
    status          VARCHAR(20) DEFAULT 'offen',
    faellig_am      DATE NOT NULL,
    bezahlt_am      DATETIME DEFAULT NULL,
    bezahlter_betrag DECIMAL(10,2) DEFAULT 0.00,
    -- Bankverbindung zum Zeitpunkt der Zahlung (historischer Audit-Trail)
    zahlung_blz     VARCHAR(8) DEFAULT NULL,
    zahlung_kto     VARCHAR(10) DEFAULT NULL,
    zahlung_iban    VARCHAR(34) DEFAULT NULL,
    -- Mahnwesen (hinzugefügt 2016 für automatischen Mahnlauf)
    mahnstufe       INT(1) DEFAULT 0 COMMENT '0=keine Mahnung, 1-3=Mahnstufe',
    letzte_mahnung  DATE DEFAULT NULL,
    mahngebuehr     DECIMAL(10,2) DEFAULT 0.00,
    angelegt_am     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_rechnungs_nr (rechnungs_nr),
    KEY idx_auftrag_id (auftrag_id),
    KEY idx_kunden_id (kunden_id),
    KEY idx_status (status),
    KEY idx_faellig_am (faellig_am),
    CONSTRAINT fk_rechnungen_auftraege FOREIGN KEY (auftrag_id) REFERENCES auftraege (id),
    CONSTRAINT fk_rechnungen_kunden FOREIGN KEY (kunden_id) REFERENCES kunden (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Rechnungen zu Auftraegen';

-- ============================================================
-- Tabelle: zahlungen
-- ============================================================
-- 2013: Angelegt für SEPA-Lastschrift-Tracking
-- 2020: bav_validiert, bav_validiert_am hinzugefügt als Audit-Trail
--       (mussten nachweisen können dass Bankverbindungen geprüft wurden)
-- ============================================================
CREATE TABLE IF NOT EXISTS zahlungen (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    rechnung_id     INT(11) NOT NULL,
    betrag          DECIMAL(10,2) NOT NULL,
    zahlungsart     VARCHAR(20) DEFAULT 'lastschrift' COMMENT 'lastschrift, ueberweisung, bar',
    blz             VARCHAR(8) DEFAULT NULL,
    kontonummer     VARCHAR(10) DEFAULT NULL,
    bic             VARCHAR(11) DEFAULT NULL,
    iban            VARCHAR(34) DEFAULT NULL,
    -- BAV-Validierungsergebnis für Audit-Trail
    bav_validiert   TINYINT(1) DEFAULT NULL COMMENT '1=geprueft und gueltig, 0=geprueft ungueltig, NULL=nicht geprueft',
    bav_validiert_am DATETIME DEFAULT NULL,
    datum           DATETIME NOT NULL,
    bemerkung       VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_rechnung_id (rechnung_id),
    KEY idx_datum (datum),
    CONSTRAINT fk_zahlungen_rechnungen FOREIGN KEY (rechnung_id) REFERENCES rechnungen (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Zahlungseingaenge';

-- ============================================================
-- Tabelle: banken_cache
-- ============================================================
-- 2009: Angelegt als lokale Kopie der Bundesbank-Bankleitzahlenliste
--       weil das Internet manchmal "zu langsam" war
-- 2013: BAV-Library eingeführt - dieser Cache sollte eigentlich abgelöst werden
-- 2013-heute: Wurde nie abgelöst. Wird nicht mehr aktiv gepflegt.
--             BankValidator::getBankName() benutzt BAV als primäre Quelle
--             und diese Tabelle als Fallback. Stand: ~12000 Einträge von 2012.
-- ============================================================
CREATE TABLE IF NOT EXISTS banken_cache (
    blz             VARCHAR(8) NOT NULL COMMENT '8-stellige Bankleitzahl',
    name            VARCHAR(200) NOT NULL,
    ort             VARCHAR(100) DEFAULT NULL,
    bic             VARCHAR(11) DEFAULT NULL,
    gueltig_bis     DATE DEFAULT NULL COMMENT 'Immer NULL, wurde nie befuellt',
    PRIMARY KEY (blz)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Lokaler Bankleitzahlen-Cache (veraltet seit BAV-Einfuehrung 2013)';

-- ============================================================
-- Tabelle: benutzer
-- ============================================================
-- 2009: Angelegt, MD5-Passwörter waren "sicher genug" damals
-- 2015: rolle ENUM erweitert um 'buchhaltung'
-- TODO seit 2018: Auf bcrypt umstellen - noch nicht passiert
-- ============================================================
CREATE TABLE IF NOT EXISTS benutzer (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    benutzername    VARCHAR(50) NOT NULL,
    passwort        VARCHAR(32) NOT NULL COMMENT 'MD5-Hash! Unsicher, TODO seit 2018: bcrypt',
    email           VARCHAR(255) DEFAULT NULL,
    name            VARCHAR(200) DEFAULT NULL COMMENT 'Anzeigename',
    rolle           ENUM('mitarbeiter','admin','buchhaltung') DEFAULT 'mitarbeiter',
    aktiv           TINYINT(1) DEFAULT 1,
    letzter_login   DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_benutzername (benutzername)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Systembenutzer (Mitarbeiter)';

-- ============================================================
-- Tabelle: rechnungs_sequence
-- ============================================================
-- Hilfstabelle für Trigger tr_rechnung_nummer_before_insert
-- Simuliert eine Sequenz pro Jahr für Rechnungsnummern
-- PROBLEM: Kann bei hoher Last Deadlocks verursachen (TABLE LOCK)
-- ============================================================
CREATE TABLE IF NOT EXISTS rechnungs_sequence (
    jahr            VARCHAR(4) NOT NULL COMMENT 'Jahr z.B. 2023',
    letzte_nr       INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (jahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Sequenztabelle fuer Rechnungsnummern-Trigger';


-- ============================================================
-- TRIGGER
-- ============================================================
-- Diese Trigger enthalten wesentliche Geschäftslogik!
-- Status-Übergänge der Aufträge werden hier gesteuert, NICHT in der Applikation.
-- Das ist historisch gewachsen und war ursprünglich als "sichere zentrale Stelle"
-- gedacht. In der Praxis bedeutet es: Wenn Aufträge komische Status haben,
-- liegt es meistens an einem dieser Trigger.
--
-- DEBUGGING-TIPP: SHOW TRIGGERS; und SHOW TRIGGER STATUS; sind deine Freunde.
-- ============================================================

DELIMITER $$

-- ============================================================
-- Trigger 1: Rechnung bezahlt → Auftrag abschließen
-- ============================================================
-- Wenn eine Rechnung auf 'bezahlt' gesetzt wird, soll der Auftrag
-- automatisch auf Status 5 (Abgeschlossen) gehen.
--
-- Das ist der EINZIGE Weg wie auftraege.status auf 5 kommt!
-- In der PHP-Applikation gibt es KEINEN Code der status=5 direkt setzt.
-- Wer das nicht weiß, sucht stundenlang in der falschen Schicht.
--
-- Erstellt: 2013 (Peter)
-- Geändert: 2018 - NOT IN (5,9) hinzugefügt damit stornierte Aufträge
--           nicht wieder geöffnet werden
-- ============================================================
CREATE TRIGGER tr_rechnung_bezahlt_after_update
AFTER UPDATE ON rechnungen
FOR EACH ROW
BEGIN
    IF NEW.status = 'bezahlt' AND OLD.status != 'bezahlt' THEN
        UPDATE auftraege
        SET status = 5,
            abgeschlossen_am = NOW()
        WHERE id = NEW.auftrag_id
          AND status NOT IN (5, 9);
    END IF;
END$$

-- ============================================================
-- Trigger 2: Neue Lieferung → Auftrag in Bearbeitung
-- ============================================================
-- Wenn eine Lieferung für einen Auftrag angelegt wird, geht der
-- Auftrag von 'Bestätigt' (2) auf 'In Bearbeitung' (3).
-- Nur wenn Status = 2, sonst nichts tun (z.B. bei Teillieferungen).
--
-- Erstellt: 2011
-- ============================================================
CREATE TRIGGER tr_lieferung_erstellt_after_insert
AFTER INSERT ON lieferungen
FOR EACH ROW
BEGIN
    UPDATE auftraege
    SET status = 3
    WHERE id = NEW.auftrag_id
      AND status = 2;
END$$

-- ============================================================
-- Trigger 3: Lieferung zugestellt → Auftrag "versendet"
-- ============================================================
-- Wenn eine Lieferung auf 'zugestellt' geht, setzt dieser Trigger
-- den Auftrag auf Status 4 (Versendet).
--
-- BUG: Das ist falsch. 'Zugestellt' sollte eigentlich Status 5 triggern,
-- aber Status 5 kommt ja über Trigger 1 (Rechnung bezahlt).
-- Status 4 heißt 'Versendet', nicht 'Zugestellt' - der Name passt auch nicht.
-- Niemand wollte das anfassen weil unklar ist welche Reports darauf basieren.
--
-- Erstellt: 2011
-- Letzte Diskussion ob das geändert werden soll: 2021 (Ergebnis: nein)
-- ============================================================
CREATE TRIGGER tr_lieferung_zugestellt_after_update
AFTER UPDATE ON lieferungen
FOR EACH ROW
BEGIN
    IF NEW.status = 'zugestellt' AND OLD.status != 'zugestellt' THEN
        UPDATE auftraege
        SET status = 4,
            lieferdatum_ist = CURDATE()
        WHERE id = NEW.auftrag_id
          AND status < 4;
    END IF;
END$$

-- ============================================================
-- Trigger 4: Auftrag bestätigt → Lagerbestand reservieren
-- ============================================================
-- Wenn ein Auftrag von Status 1 auf Status 2 geht, werden die
-- bestellten Mengen vom Lagerbestand abgezogen.
--
-- BEKANNTE BUGS:
-- 1. Kein Check ob Lagerbestand ausreichend ist → kann negativ werden
-- 2. Bei Stornierung (Status 9) wird der Lagerbestand NICHT zurückgebucht
--    Das wurde vergessen und war schwer nachzuimplementieren ohne Reports zu brechen.
--    Lagerbestand-Daten sind daher nach Stornierungen immer falsch.
--    Workaround: Lagerbestand wird jeden Monat manuell korrigiert.
--
-- Erstellt: 2010
-- ============================================================
CREATE TRIGGER tr_auftrag_bestaetigt_after_update
AFTER UPDATE ON auftraege
FOR EACH ROW
BEGIN
    IF NEW.status = 2 AND OLD.status = 1 THEN
        UPDATE artikel a
        INNER JOIN auftrag_positionen ap ON a.id = ap.artikel_id
        SET a.lagerbestand = a.lagerbestand - ap.menge
        WHERE ap.auftrag_id = NEW.id
          AND ap.artikel_id IS NOT NULL;
    END IF;
END$$

-- ============================================================
-- Trigger 5: Rechnungsnummer automatisch generieren
-- ============================================================
-- Generiert eine eindeutige Rechnungsnummer im Format RE-YYYY-NNNNN
-- über eine Sequenz-Tabelle (rechnungs_sequence).
--
-- PROBLEM: Die Funktion getNextRechnungsNr() in helper.php versucht
-- dasselbe zu tun und läuft manchmal davor. Dann gibt es doppelte
-- Nummernvergabe-Versuche und der UNIQUE KEY wirft einen Fehler.
-- Kurzfristiger Fix: getNextRechnungsNr() wurde nie aus helper.php entfernt
-- weil noch alter Code darauf verweist.
--
-- PERFORMANCE: Bei vielen gleichzeitigen Rechnungen kann der INSERT INTO
-- rechnungs_sequence ... ON DUPLICATE KEY UPDATE zu Deadlocks führen.
-- Bisher (2023) ist das nur einmal passiert und wurde mit Neustart "gelöst".
--
-- Erstellt: 2013
-- ============================================================
CREATE TRIGGER tr_rechnung_nummer_before_insert
BEFORE INSERT ON rechnungen
FOR EACH ROW
BEGIN
    DECLARE v_year VARCHAR(4);
    DECLARE v_seq  INT;

    SET v_year = YEAR(NOW());

    -- Sequenz für dieses Jahr erhöhen oder neu anlegen
    INSERT INTO rechnungs_sequence (jahr, letzte_nr)
    VALUES (v_year, 1)
    ON DUPLICATE KEY UPDATE letzte_nr = letzte_nr + 1;

    -- Aktuelle Sequenznummer holen
    SELECT letzte_nr INTO v_seq
    FROM rechnungs_sequence
    WHERE jahr = v_year;

    -- Rechnungsnummer zusammenbauen: RE-2023-00001
    SET NEW.rechnungs_nr = CONCAT('RE-', v_year, '-', LPAD(v_seq, 5, '0'));
END$$

DELIMITER ;

SET foreign_key_checks = 1;

-- ============================================================
-- Initialdaten: Admin-Benutzer
-- ============================================================
-- Passwort: 'admin123' als MD5
-- Bitte in Produktion ändern. Wurde nie geändert (Stand 2023).
INSERT IGNORE INTO benutzer (benutzername, passwort, email, name, rolle, aktiv, letzter_login)
VALUES ('admin', MD5('admin123'), 'admin@northwind-logistics.de', 'Administrator', 'admin', 1, NULL);

INSERT IGNORE INTO benutzer (benutzername, passwort, email, name, rolle, aktiv, letzter_login)
VALUES ('buchhaltung', MD5('buch2009'), 'buchhaltung@northwind-logistics.de', 'Buchhaltung', 'buchhaltung', 1, NULL);
