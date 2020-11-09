<?php
namespace EmployeeService\Controller;
use EmployeeService\SqlConnect;
/**
 * Controller(ek) ősosztálya, főként gyakran használt helper funkciókat foglal magába
 */
abstract class BaseController {
  /**
   * Beállítja headert jsonra a megadott http kóddal
   * @param integer $code http response code
   */
  protected static function setJsonHeaders(int $code=200) {
    header('Content-Type: application/json');
    http_response_code($code);
  }
  /**
   * JSON választ készít tömbből
   * @param  array   $response választ tartalmazó tömb
   * @param  integer $code     http_response_code
   * @return string            encoded json
   */
  protected static function makeJsonResponse(array $response, int $code=200) : string {
    static::setJsonHeaders($code);
    return json_encode($response);
  }
  /**
   * az adott formátumú lekérdezéshez készít lapozható json formátumú választ
   * @param  string  $query limit és offset mentes mysql query
   * @param  int     $page  oldalszám
   * @param  integer $limit oldalméret (opcionális)
   * @return string        Json formátumú lista
   */
  protected static function getPagedJson(string $query, string $count_query, int $page, int $limit) : string {
    //page > 0 && page >= pages
    $offset = ($page-1)*$limit;
    $db = new SqlConnect();
    $result = $db->query("$query LIMIT $limit OFFSET $offset");
    if(!$result) {
      return self::makeJsonResponse([
          'data' => [],
          'count' => 0,
          'all' => 0,
          'pages' => 1,
          'page' => 1,
          'limit' => $limit
        ], 200);
    }
    $all = $db->query("$count_query");
    $all_count = $all->fetch_row();
    $pages = intval($all_count[0] / $limit) + 1;
    $data = $result->fetch_all(MYSQLI_ASSOC);
    return self::makeJsonResponse([
        'data' => $data,
        'count' => $result->num_rows,
        'all' => intval($all_count[0]),
        'pages' => $pages,
        'page' => $page,
        'limit' => $limit
      ], 200);

  }
  /**
   * Invalid függvényhívásra készített válasz
   * @return string message
   */
  protected static function invalidArgsResponse() : string{
    http_response_code(422);
    return 'Unprocessable entity.';
  }
}
