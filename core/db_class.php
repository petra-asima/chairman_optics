<?php
// Opens one database connection that every Model class shares.

class Database
{
    protected $conn;
    private static $sharedConn = null;

    public function __construct()
    {
        if (self::$sharedConn === null) {
            require_once __DIR__ . '/db_cred.php';

            // Turn MySQL errors into exceptions so they can be caught
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            try {
                self::$sharedConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                self::$sharedConn->set_charset('utf8mb4');
            } catch (mysqli_sql_exception $ex) {
                log_error('DB connection failed: ' . $ex->getMessage());
                http_response_code(500);
                die('Sorry, the site is having trouble right now. Please try again later.');
            }
        }
        $this->conn = self::$sharedConn;
    }
}