<?php
namespace EmployeeService;
use mysqli;
/**
 * Mysql kapcsolatot megvalósító fv
 * a config db-ben megadott mysql kapcsolathoz szükséges adatokat használja fel
 */
class SqlConnect extends mysqli {
  function __construct() {
    $dbConfig = static::loadConfig();
    parent::__construct($dbConfig['host'],$dbConfig['user'],$dbConfig['password'], $dbConfig['name']);
    if ($this -> connect_errno) {
      echo "Failed to connect to MySQL: " . $this -> connect_error;
      exit;
    }
  }

  /**
   * Beállítások betöltését végző fv
   * @return array
   */
  protected static function loadConfig() : array {
    return require __DIR__ . '/../config/db.php';
  }
}
