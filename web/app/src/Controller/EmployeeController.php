<?php
namespace EmployeeService\Controller;
use EmployeeService\SqlConnect;

class EmployeeController extends BaseController {
  const LIMIT = 20;
  /**
   * Employee entitásra engedélyezett módosítható mezők listája
   * @var array
   */
  const EMPLOYEE_MODIFY_ARGS = [
    'first_name','last_name', 'birth_date', 'gender', 'hire_date'
  ];
  /**
   * Engedélyezett filterek, ezeket lehet szűrni
   * @var array
   */
  const ENABLED_FILTERS = [
    'first_name', 'last_name', 'title', 'hire_date', 'dept_name'
  ];
  /**
   * Engedélyezett rendező mezők
   * @var array
   */
  const ENABLED_ORDERS = [
    'first_name', 'last_name', 'title', 'hire_date', 'dept_name'
  ];
  /**
   * $_GET - MySql kulcspárok
   * @var array
   */
  const QUERY_KEYS = [
    'first_name' => 'employees.first_name',
    'last_name' => 'employees.last_name',
    'hire_date' => 'employees.hire_date',
    'title' => 'titles.title',
    'dept_name' => 'departments.dept_name'
  ];
  /**
   * összeszedi a GET változó alapján a rendezési feltételeket
   * @return array [field => "field_name", "direction" => "ASC|DESC"] or []
   */
  protected static function getOrder() : array {
    $order = [];
    if(isset($_GET['order'])) {
      if(in_array($_GET['order'],self::ENABLED_ORDERS)) {
        if(
          !isset($_GET['order_direction'])
          xor ($_GET['order_direction'] == 1 || $_GET['order_direction'] == 0)
        ){
          $order['field'] = $_GET['order'];
          $order['direction'] = isset($_GET['order_direction']) ? $_GET['order_direction'] : 1 ;
        }
      }
    }
    return $order;
  }
  /**
   * Elkészti a lekérdezés rendezés részét
   * @return string query part
   */
  protected static function getOrderQuery() : string {
    $query = '';
    $order = self::getOrder();
    if(count($order) > 0) {
      $query = 'ORDER BY ' . self::QUERY_KEYS[$order['field']] . ' ' . ($order['direction'] == 1 ? 'ASC' : 'DESC');
    }
    return $query;
  }
  /**
   * Összegyűjti a szűrőfeltételeket
   * @return array [$key=>$value,...]
   */
  protected static function getFilters() : array {
    $filters = [];
    if(isset($_GET['filter']) && is_array($_GET['filter'])) {
      foreach ($_GET['filter'] as $key => $value) {
        if(in_array($key,self::ENABLED_FILTERS) && $value && strlen($value) > 0) {
          $filters[$key] = $value;
        }
      }
    }
    return $filters;
  }
  /**
   * Ellenőrzi hogy a oldalszám meghatározásához elég-e az employee table
   * @return bool
   */
  protected static function filterIsNotOnlyEmployeeTable() : bool {
    $filters = self::getFilters();
    return array_key_exists('title', $filters) || array_key_exists('dept_name', $filters);
  }
  /**
   * Elkészti a szűréshez tartozó lekérdezés részét
   * @return string query_part
   */
  protected static function getFilterQuery() : string {
    $query = '';
    $filters = self::getFilters();
    if(count($filters) > 0) {
      $db = new SqlConnect();
      $queries = [];
      foreach ($filters as $key => $value) {
        $queries[] = self::QUERY_KEYS[$key] . '=' . '"' . $db->real_escape_string($value) . '"';
      }
      $query = 'WHERE ' . join(' AND ', $queries);
      $db->close();
    }
    return $query;
  }
  /**
   * Listázást megvalósító fv
   * @return [type] [description]
   */
  public static function list() {
    //query for a list
    $query = "SELECT employees.*, departments.dept_name, salaries.salary, titles.title
    FROM employees
    INNER JOIN current_dept_emp
    ON current_dept_emp.emp_no = employees.emp_no
    INNER JOIN departments
    ON current_dept_emp.dept_no = departments.dept_no
    INNER JOIN dept_emp_latest_date
    ON dept_emp_latest_date.emp_no = employees.emp_no
    INNER JOIN (
      SELECT emp_no, max(from_date) as from_date
    	FROM salaries
    	GROUP BY emp_no
    ) as actual_salaries
    ON employees.emp_no = actual_salaries.emp_no
    INNER JOIN salaries
    ON salaries.emp_no = employees.emp_no AND salaries.from_date = actual_salaries.from_date
    INNER JOIN (
    	SELECT emp_no, MAX(from_date) as from_date
        FROM titles
        GROUP BY emp_no
    ) as actual_titles
    ON employees.emp_no = actual_titles.emp_no
    INNER JOIN titles
    ON employees.emp_no = titles.emp_no AND titles.from_date = actual_titles.from_date";
    //query for counting (ez alapján lesznek az oldalszámok elkésztve)
    $count_query = 'SELECT COUNT(*) FROM employees';
    //get Filters
    $filterQ = self::getFilterQuery();
    if(strlen($filterQ) > 0) {
      $query .= " $filterQ";
      $count_query .= " $filterQ";
    }
    //get ordering
    $orderQ = self::getOrderQuery();
    if(strlen($orderQ) > 0) {
      $query .= " $orderQ";
    }
    //get page
    if(isset($_GET['page']) && is_int(intval($_GET['page'])) && intval($_GET['page']) > 0 ) {
      $page = intval($_GET['page']);
    } else {
      $page = 1;
    }
    if(self::filterIsNotOnlyEmployeeTable()) {
      $count_query = "SELECT COUNT(*) as `all` FROM ($query) as full_table;";
    }
    //JSON formában tér vissza
    return self::getPagedJson($query, $count_query, $page, self::LIMIT);
  }
  /**
   * Employee módosítását megoldó fv
   *
   */
  public static function modify() {
    //required emp_no
    if(!isset($_POST['emp_no'])) {
      //http error
      return static::makeJsonResponse(['message'=>'Missing emp_no parameters'], 400);
    }
    $data = [];
    foreach (self::EMPLOYEE_MODIFY_ARGS as $arg) {
      if(isset($_POST[$arg])) {
        $data[$arg] = $_POST[$arg];
      }
    }
    if(count($data) > 0) {
      $db =  new SqlConnect();
      //make query
      $query = 'UPDATE employees SET ';
      $columns = [];
      foreach ($data as $key => $value) {
        $columns[] = "$key=\"" . $db->real_escape_string($value) . '"';
      }
      $query .= join(',', $columns);
      $query .= ' WHERE emp_no="' . $db->real_escape_string($_POST['emp_no']) . '";';
      $db->query($query);
      $result = $db->query('SELECT * FROM employees WHERE emp_no ="' . $db->real_escape_string($_POST['emp_no']) . '";' );
      $employee = $result->fetch_all(MYSQLI_ASSOC)[0];
      $db->close();
      return static::makeJsonResponse($employee, 201);
    }
  }
  /**
   * Employee törlését végző fv
   */
  public function delete() {
    if(isset($_POST['emp_no']) && $_POST['emp_no'] == intval($_POST['emp_no'])) {
      $db = new SqlConnect();
      $succes = $db->query('DELETE FROM employees WHERE emp_no=' . $_POST['emp_no'] .';' );
      $db->close();
      if(!$succes) {
        return static::makeJsonResponse(['message' => 'Failed to delete employee'], 400);
      } else {
        return static::makeJsonResponse(['message' => 'Success'], 200);
      }
    }
  }
}
