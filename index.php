<?php

/**
 * RPC-QL    - RPC-QL is a method of passing query language (QL)
 *           - statements through a whitelist dictionary using
 *           - remote procedure call (RPC).
 *
 * @package  RPC-QL (Query Language over RPC)
 * @author   Raymund John Ang <raymund@open-nis.org>
 * @license  MIT License
 */

// Process request data using JSON-RPC 2.0 specification
$data = json_decode( file_get_contents('php://input'), TRUE );
$jsonrpc = $data['jsonrpc'];
$method = $data['method'];
$params = $data['params'];
$id = $data['id'];

// SQL terms ['display_name', 'actual_query', 'description']
$whitelist_sql = [['SELECT', 'SELECT', 'SQL'],
              ['FROM', 'FROM', 'SQL'],
              ['WHERE', 'WHERE', 'SQL'],
              ['AND', 'AND', 'SQL'],
              ['OR', 'OR', 'SQL'],
              ['JOIN', 'JOIN', 'SQL'],
              ['LIMIT', 'LIMIT', 'SQL'],
              ['IN', 'IN', 'SQL'],
              ['LIKE', 'LIKE', 'SQL'],
              ['OFFSET', 'OFFSET', 'SQL'],
              ['LIMIT', 'LIMIT', 'SQL'],
              ['INSERT', 'INSERT', 'SQL'],
              ['UPDATE', 'UPDATE', 'SQL'],
              ['DELETE', 'DELETE', 'SQL'],
              ['INTO', 'INTO', 'SQL'],
              ['VALUES', 'VALUES', 'SQL'],
              [' ', ' ', 'SQL'],
              ['_', '_', 'SQL'],
              ['%', '%', 'SQL'],
              ['*', '*', 'SQL'],
              ['=', '=', 'SQL'],
              ['<', '<', 'SQL'],
              ['>', '>', 'SQL'],
              [',', ',', 'SQL'],
              ['?', '?', 'SQL'],
              ['(', '(', 'SQL'],
              [')', ')', 'SQL'],
              ["'", "'", 'SQL'],
              ['"', '"', 'SQL']];

// Table & field names, and description ['display_name', 'actual_query', 'description']
$whitelist_names = [['persons', 'db_persons', 'Persons Table'], // Table
              ['person_id', 'db_person_id', 'integer - ID number'], // Field
              ['person_name', 'db_person_name', 'string - Name'], // Field
              ['person_gender', 'db_person_gender', 'string - M or F'], // Field
              ['person_birthdate', 'db_person_birthdate', 'date - YYYY-MM-DD']]; // Field];

$whitelist = array_merge($whitelist_sql, $whitelist_names); // Whitelist Dictionary

// Show Whitelist Dictionary with GET request.
$whitelist_display = array();
foreach ($whitelist as $array) {
  $whitelist_display[] = "$array[0] => $array[2]";
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') echo json_encode($whitelist_display) . PHP_EOL;


/**
 * RPC-QL Function accessed as an API
 */

function rpc_ql()
{

  global $whitelist, $whitelist_names, $jsonrpc, $method, $params, $id;

  $errors = array(); // Log errors

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') $errors[] = 'Request method should be POST.'; // Require POST
  if ( empty($jsonrpc) || empty($method) || empty($params) || empty($id) ) $errors[] = 'Please set "jsonrpc", "method", "params", and request "id".'; // Require JSON-RPC 2.0 specification.
  if (! empty($jsonrpc) && $jsonrpc !== '2.0') $errors[] = 'JSON-RPC version should be "2.0".'; // JSON-RPC version 2.0
  if (empty($params['token'])) $errors[] = 'Please set token as parameter.'; // Require token
  if (empty($params['query'])) $errors[] = 'Please set query as parameter.'; // Require query
  if (! isset($params['data'])) $errors[] = 'Please set data as parameter.'; // Require data; "data":[] if empty

  // Set token, query and data parameters.
  if (! empty($params['token'])) $token = $params['token'];
  if (! empty($params['query'])) $query = $params['query'];
  if (! empty($params['data'])) $data = $params['data'];

  // Token validation - token should be set as '12345'
  if (! hash_equals('12345', $token)) $errors[] = 'Token authentication has failed.';

  // Display errors and exit
  if (! empty($errors)) {
  foreach ($errors as $row) echo $row . PHP_EOL;
  exit;
  }

  // Retrieve whitelist display names
  $display_names = array();
  foreach ($whitelist as $row) {
    $display_names[] = $row[0];
  }

  /* Prepare $query for array conversion */
  // Remove special characters attached to whitelisted terms from $query.
  $query_remove = $query; // Initial conversion of $query to $query_remove.
  $query_remove = str_replace( '%', '', $query_remove );
  $query_remove = str_replace( ',', '', $query_remove );
  $query_remove = str_replace( '(', '', $query_remove );
  $query_remove = str_replace( ')', '', $query_remove );
  $query_remove = str_replace( "'", '', $query_remove );
  $query_remove = str_replace( '"', '', $query_remove );
  $query_array = explode( ' ', $query_remove );

  // Declare an error if not in Whitelist Dictionary display names (as string)
  foreach ($query_array as $row) {
    if ( ! stristr(implode('::', $display_names), $row) ) {
      $invalid_query[] = $row;
    }
  }

  // Invalid query error
  if (! empty($invalid_query)) {
    $error = 'The query is not valid. Please verify with whitelisted terms.';
    $response = ['jsonrpc' => $jsonrpc, 'result' => NULL, 'error' => ['code' => 32602, 'message' => $error], 'id' => $id];
    echo json_encode($response);
    exit;
  }

  /* Proceed with SQL execution. */
  // Convert whitelisted terms to actual database table and field names.
  $sql = $query; // Initial conversion of $query to $sql.
  foreach ($whitelist as $row) {
    $sql = str_ireplace( $row[0], $row[1], $sql );
  }

  // Pass an SQL statement over JSON-RPC using PHP PDO with '?' as placeholder, and $data OR $params['data'] as data.
  $servername = "localhost"; // server
  $username = "user"; // username
  $password = "pass"; // password
  $database = "rpc-ql"; // database

  $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
  $conn->setAttribute( PDO::ATTR_EMULATE_PREPARES, FALSE ); // Array binding without using $stmt->bindValue()

  // Convert to actual query statement.
  $stmt = $conn->prepare("$sql");
  if ($stmt == FALSE) exit(json_encode(['jsonrpc' => $jsonrpc, 'error' => ['code' => 32602, 'message' => 'Invalid query statement preparation.'], 'id' => $id])); // Invalid SQL preparation
  $stmt->execute($data);
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Set $error to NULL if $sql is a valid query. (JSON-RPC 2.0)
  if (! empty($result) || $result !== FALSE ) $error = NULL;

  /* Result Output */
  // If $result from SELECT query is empty or FALSE, show error in JSON-RPC 2.0 format.
  if ( substr($sql, 0, 6) == 'SELECT' && isset($result) && (empty($result) || $result == FALSE ) ) exit(json_encode(['jsonrpc' => $jsonrpc, 'error' => ['code' => 32602, 'message' => 'No results found.'], 'id' => $id]));

  // If non-SELECT query is valid, show response result member as 'Success' in JSON-RPC 2.0 format.
  if ( substr($sql, 0, 6) !== 'SELECT' && isset($result) && empty($result)) $result = 'Success';

  $result = json_encode($result);
  foreach ($whitelist_names as $row) {
    $result = str_replace( $row[1], $row[0], $result );
  }
  $result = json_decode($result);

  // Show output in JSON-RPC 2.0 format.
  $response = ['jsonrpc' => $jsonrpc, 'result' => $result, 'error' => $error, 'id' => $id];
  echo json_encode($response);

}

/**
 * Execute procedure if function exists.
 * Else, show error using JSON-RPC 2.0 format.
 */

if (function_exists($method)) {
  return $method();
} else {
  $error = 'Sorry. The RPC method does not exist.';
  $response = ['jsonrpc' => '2.0', 'result' => NULL, 'error' => ['code' => 32601, 'message' => $error], 'id' => $id];
  echo json_encode($response);
  exit;
}