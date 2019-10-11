<?php

/**
 * RPC-QL    - RPC-QL is a method of passing query language (QL)
 *           - statements using remote procedure call (RPC).
 *
 * @package  RPC-QL (Query Language over RPC)
 * @author   Raymund John Ang <raymund@open-nis.org>
 * @license  MIT License
 */

// Process POSTed JSON data using JSON-RPC 2.0 specification
$data = json_decode( file_get_contents('php://input'), TRUE );
$jsonrpc = $data['jsonrpc'];
$method = $data['method'];
$params = $data['params'];
$id = $data['id'];

/** Table and column fields Whitelist Dictionary **/
// 'SQL' means part of the SQL terminology.
$whitelist = ['SELECT' => 'SQL',
              'FROM' => 'SQL',
              'WHERE' => 'SQL',
              'AND' => 'SQL',
              'OR' => 'SQL',
              'IN' => 'SQL',
              'LIKE' => 'SQL',
              'INSERT' => 'SQL',
              'INTO' => 'SQL',
              'VALUES' => 'SQL',
              ' ' => 'SQL',
              '_' => 'SQL',
              '%' => 'SQL',
              '*' => 'SQL',
              '=' => 'SQL',
              '<' => 'SQL',
              '>' => 'SQL',
              ',' => 'SQL',
              '?' => 'SQL',
              '(' => 'SQL',
              ')' => 'SQL',
              "'" => 'SQL',
              '"' => 'SQL',
              // Table and field names, and description
              'persons' => 'Persons Table',
              'person_id' => 'integer - ID number',
              'person_name' => 'string - Name',
              'person_gender' => 'string - M or F',
              'person_birthdate' => 'date - YYYY-MM-DD'];

// Show Whitelist Dictionary with GET request.
if ($_SERVER['REQUEST_METHOD'] == 'GET') echo json_encode($whitelist);

/**
 * RPC-QL Function accessed as an API
 */

function rpc_ql()
{

  global $whitelist, $jsonrpc, $method, $params, $id;

  // Access API functionality with POST request.
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Require request members based on JSON-RPC 2.0.
    if ( empty($jsonrpc) || empty($method) || empty($params) || empty($id) ) exit('Please set "jsonrpc", "method", "params", and request "id".');

    // Require token and query as parameters.
    if (empty($params['token'])) exit('Please set token as parameter.');
    if (empty($params['query'])) exit('Please set query as parameter.');
    if (isset($params['token'])) $token = $params['token'];
    if (isset($params['query'])) $query = $params['query'];
    if (isset($params['query_data'])) $query_data = $params['query_data'];
    
    // Token validation - token should be set as 12345
    if ( ! hash_equals(hash('sha256', 12345), hash('sha256', $token)) ) exit('Token authentication has failed.');
    
    // Retrieve whitelist array keys
    $whitelist_keys = array_keys($whitelist);

    /** Prepare $query for array conversion **/
    // Remove special characters attached to whitelisted terms from $querry.
    $query_remove = str_replace( '%', '', $query ); // Initial conversion of attached characters.
    $query_remove = str_replace( ',', '', $query_remove ); // Succeeding conversion of $query_remove.
    $query_remove = str_replace( '(', '', $query_remove );
    $query_remove = str_replace( ')', '', $query_remove );
    $query_remove = str_replace( "'", '', $query_remove );
    $query_remove = str_replace( '"', '', $query_remove );
    $query_array = explode( ' ', $query_remove );

    // Declare an error if not in Whitelist Dictionary keys (as string)
    foreach ($query_array as $query_array) {
      if ( ! stristr(implode('::', $whitelist_keys), $query_array) ) {
        $error_query[] = $query_array;
      }
    }

    // Proceed with conversion to database field names if without errors.
    if (empty($error_query)) {

      // Convert whitelisted terms to actual database table and field names.
      $query_conv = str_ireplace( 'persons', 'db_persons', $query ); // Persons Table. Initial conversion of $query.
      $query_conv = str_ireplace( 'person_id', 'db_person_id', $query_conv ); // Succeeding conversion of $query_conv.
      $query_conv = str_ireplace( 'person_name', 'db_person_name', $query_conv );
      $query_conv = str_ireplace( 'person_gender', 'db_person_gender', $query_conv );
      $query_conv = str_ireplace( 'person_birthdate', 'db_person_birthdate', $query_conv );

      // Set $error to null if $query_conv is a valid query. (JSON-RPC 2.0)
      if ($query_conv !== FALSE) $error = null;

      /*** Pass an SQL statement over JSON-RPC using PHP PDO with '?' as placeholder, and $query_data OR $params['query_data'] as data. ***/
      $servername = "localhost"; // server
      $username = "root"; // username
      $password = ""; // password
      $database = "rpc-ql"; // database

      $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);

      // Use converted query string in actual query statement.
      $stmt = $conn->prepare("$query_conv");
      $stmt->execute($query_data);
      $result = $stmt->fetchAll();

      // If $result from SELECT query is empty or FALSE, show error in JSON-RPC 2.0 format.
      if ( substr($query_conv, 0, 6) == 'SELECT' && isset($result) && (empty($result) || $result == FALSE ) ) exit(json_encode(['jsonrpc' => $jsonrpc, 'error' => ['code' => 32602, 'message' => 'No results found.'], 'id' => $id]));
      
      // If non-SELECT query is valid, show response result member as 'Success' in JSON-RPC 2.0 format.
      if ( substr($query_conv, 0, 6) !== 'SELECT' && isset($result) && empty($result)) $result = 'Success';
      
      // Show output in JSON-RPC 2.0 format.
      $response = ['jsonrpc' => $jsonrpc, 'result' => $result, 'error' => $error, 'id' => $id];
      echo json_encode($response);

    } else {

      // Failed validation
      $error_message = "The query is not valid. Please verify with whitelisted terms.";
      $response = ['jsonrpc' => $jsonrpc, 'result' => null, 'error' => ['code' => 32602, 'message' => $error_message], 'id' => $id];
      echo json_encode($response);

    }

  }

}

// Execute method if function exists, else, show error using JSON-RPC 2.0 format.
if ( $_SERVER['REQUEST_METHOD'] == 'POST' && function_exists($method) ) {
  return $method();
} elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' && ! function_exists($method) ) {
  $error_message = 'Sorry. The RPC method does not exist.';
  $response = ['jsonrpc' => $jsonrpc, 'result' => null, 'error' => ['code' => 32601, 'message' => $error_message], 'id' => $id];
  echo json_encode($response);
}