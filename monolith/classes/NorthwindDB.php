<?php
/**
 * Datenbankwrapper für Northwind Logistics
 * Erstellt: 2009, Peter Müller
 * Letzte Änderung: 2016, irgendwer hat hier rumgepfuscht
 *
 * ACHTUNG: mysql_* ist veraltet, aber umstellen ist zu aufwändig.
 * Jira-Ticket NW-412 ist seit 2015 offen.
 */
class NorthwindDB {

    private $connection;

    // Singleton-Pattern -- aber wird auch direkt mit new NorthwindDB() aufgerufen
    // in manchen Pages. Beides koexistiert friedlich (meistens).
    private static $instance = null;

    public function __construct($host, $user, $pass, $dbname) {
        $this->connection = mysql_connect($host, $user, $pass);
        if (!$this->connection) {
            // Im Produktivbetrieb sollte hier nie die DB-Zugangsdaten sichtbar sein.
            // Aber error_reporting ist eh aus, also egal.
            die('Datenbankverbindung fehlgeschlagen: ' . mysql_error());
        }
        if (!mysql_select_db($dbname, $this->connection)) {
            die('Datenbank nicht gefunden: ' . $dbname . ' -- ' . mysql_error($this->connection));
        }
        mysql_query("SET NAMES 'utf8'", $this->connection);
        mysql_query("SET time_zone = '+01:00'", $this->connection);
    }

    /**
     * Singleton-Instanz zurückgeben.
     * Wird NUR genutzt wenn man keine Referenz auf $db hat.
     * Sonst new NorthwindDB() verwenden (historisch gewachsen).
     */
    public static function getInstance() {
        if (self::$instance === null) {
            global $db_host, $db_user, $db_pass, $db_name;
            self::$instance = new NorthwindDB($db_host, $db_user, $db_pass, $db_name);
        }
        return self::$instance;
    }

    /**
     * SQL-Query ausführen.
     * Bei Fehler: Seite mit SQL im Fehlertext beenden.
     * Das ist "praktisch" für Debugging aber ein Sicherheitsproblem.
     *
     * @param string $sql
     * @return resource
     */
    public function query($sql) {
        $result = mysql_query($sql, $this->connection);
        if ($result === false) {
            // TODO: Logging statt die() -- NW-203, seit 2013 offen
            $fehler = mysql_error($this->connection);
            // Achtung: gibt SQL im Browser aus! Nur "intern" OK.
            die('<pre>Datenbankfehler: ' . htmlspecialchars($fehler) . "\n\nSQL:\n" . htmlspecialchars($sql) . '</pre>');
        }
        return $result;
    }

    /**
     * Alle Zeilen aus Result holen.
     *
     * @param resource $result
     * @return array
     */
    public function fetchAll($result) {
        $rows = array();
        while ($row = mysql_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysql_free_result($result);
        return $rows;
    }

    /**
     * Eine Zeile aus Result holen.
     *
     * @param resource $result
     * @return array|null
     */
    public function fetchOne($result) {
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row ? $row : null;
    }

    /**
     * Wert für SQL escapen.
     *
     * @param string $val
     * @return string
     */
    public function escape($val) {
        return mysql_real_escape_string($val, $this->connection);
    }

    /**
     * ID der zuletzt eingefügten Zeile.
     *
     * @return int
     */
    public function lastInsertId() {
        return mysql_insert_id($this->connection);
    }

    /**
     * Anzahl betroffener Zeilen (UPDATE/DELETE).
     *
     * @return int
     */
    public function affectedRows() {
        return mysql_affected_rows($this->connection);
    }

    /**
     * Rohe Verbindung zurückgeben.
     * Wird benötigt wenn mysql_real_escape_string() direkt aufgerufen wird
     * ohne über diese Klasse zu gehen (passiert leider oft).
     *
     * @return resource
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Verbindung trennen.
     * Wird selten aufgerufen -- PHP macht das am Ende des Requests eh.
     */
    public function close() {
        if ($this->connection) {
            mysql_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Anzahl Zeilen in Result.
     *
     * @param resource $result
     * @return int
     */
    public function numRows($result) {
        return mysql_num_rows($result);
    }
}
