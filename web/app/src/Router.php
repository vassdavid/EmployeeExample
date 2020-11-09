<?php
namespace EmployeeService;
class Router {
  const ENABLED_METHODS = [
    'list',
    'modify',
    'delete'
  ];
  function __construct() {
    $this->getActualRoute();
  }
  protected function getActualRoute() {
    if(
      isset($_GET['entity'], $_GET['action'])
      && $_GET['entity'] = 'employee'
    ) {
      //hívott kontroller metódus ellenőrzése
      if(in_array($_GET['action'], self::ENABLED_METHODS)){
        $class = '\EmployeeService\Controller\EmployeeController';
        echo call_user_func("$class::{$_GET['action']}");
      } else{
        http_response_code(400);
        echo "Method is not exists";
      }
    } elseif(!isset($_GET['entity'])) {
      echo file_get_contents(__DIR__ . '/view/index.html');
    } else {
      //not found
      http_response_code(404);
      echo "NOT FOUND";
    }
  }
}
