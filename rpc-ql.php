<?php

/**
 * RPC-QL    - RPC-QL is a query language based on SQL and accessed
 *           - over remote procedure call (RPC) API.
 *
 * @package  RPC-QL (SQL on JSON-RPC)
 * @author   Raymund John Ang <raymund@open-nis.org>
 * @license  MIT License
 */

// Process POSTed JSON data using JSON-RPC 2.0 specification
$data = json_decode( file_get_contents('php://input'), true );
$jsonrpc = $data['jsonrpc'];
$method = $data['method'];
$params = $data['params'];
$id = $data['id'];

// Table and column fields whitelist dictionary
// 'SQL' means part of the SQL terminology
$whitelist = ['SELECT' => 'SQL',
              'FROM' => 'SQL',
              'WHERE' => 'SQL',
              'AND' => 'SQL',
              'OR' => 'SQL',
              'IN' => 'SQL',
              'LIKE' => 'SQL',
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
              // Table and field names
              'persons' => 'Persons table',
              'person_id' => 'integer - The ID number of the person',
              'person_name' => 'string - Name of the person',
              'person_gender' => 'string - Male or Female',
              'person_birthdate' => 'string - format YYYY-MM-DD',
              'places' => 'Places table',
              'place_id' => 'integer - The ID number of the place',
              'place_name' => 'string - Name of the place',
              'place_state' => 'string - State where the place is located'];

// Show dictionary with GET request.
if ($_SERVER['REQUEST_METHOD'] == 'GET') echo json_encode($whitelist);

// Remote API function
function rpc_ql()
{

  global $whitelist, $jsonrpc, $method, $params, $id;

  // Access API functionality with POST request.
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Require parameters based on JSON-RPC 2.0 and SQL
    if ( empty($jsonrpc) || empty($method) || empty($params['token']) || empty($params['query']) || empty($id) ) exit('Please set "jsonrpc", "method", "token" and "query" parameters, and request "ID".');
    $token = $params['token'];
    $query = $params['query'];
    $query_data = $params['query_data'];
    
    // Token validation - token should be 12345
    if ( ! hash_equals(hash('sha256', 12345), hash('sha256', $token)) ) exit('Token authentication has failed.');
    
    // Retrieve whitelist array keys
    $whitelist_keys = array_keys($whitelist);

    // Prepare $query for array conversion
    // Remove special characters attached to whitelisted terms from $querry array
    $query_cleansed = str_replace( '%', '', $query ); // Initial conversion of attached characters.
    $query_cleansed = str_replace( ',', '', $query_cleansed ); // Succeeding conversion of $query_cleansed.
    $query_cleansed = str_replace( '(', '', $query_cleansed );
    $query_cleansed = str_replace( ')', '', $query_cleansed );
    $query_cleansed = str_replace( "'", '', $query_cleansed );
    $query_cleansed = str_replace( '"', '', $query_cleansed );
    $query_array = explode( ' ', $query_cleansed );

    // Declare an error if not in whitelist dictionary
    foreach ($query_array as $query_array) {
      if ( ! stristr(implode('::', $whitelist_keys), $query_array) ) {
        $error_query[] = $query_array;
      }
    }

    // Continue with conversion to database field names if without errors.
    if ( empty($error_query) ) {

      // Table and column field names converter
      $mod_query = str_ireplace( 'persons', 'db_persons', $query ); // Persons table; Initial conversion of $query.
      $mod_query = str_ireplace( 'person_id', 'db_person_id', $mod_query ); // Succeeding conversion of $mod_query.
      $mod_query = str_ireplace( 'person_name', 'db_person_name', $mod_query );
      $mod_query = str_ireplace( 'person_gender', 'db_person_gender', $mod_query );
      $mod_query = str_ireplace( 'person_birth_date', 'db_person_birthdate', $mod_query );
      $mod_query = str_ireplace( 'places', 'db_places', $mod_query ); // Places table
      $mod_query = str_ireplace( 'place_id', 'db_place_id', $mod_query );
      $mod_query = str_ireplace( 'place_name', 'db_place_name', $mod_query );
      $mod_query = str_ireplace( 'place_state', 'db_place_state', $mod_query );

      // Set $error to null if $mod_query is a valid query. (JSON-RPC 2.0)
      if ($mod_query !== false) $error = null;

      /*** Perform SQL execution using PHP PDO with '?' as placeholder and $query_data OR $params['query_data'] as the array of data. ***/
      
      $response = ['jsonrpc' => $jsonrpc, 'result' => $mod_query, 'error' => $error, 'id' => $id];
      echo json_encode($response);
      echo ' -- POSTed query data --> ' . json_encode($query_data);

    } else {

      // Failed validation
      $error_message = "The query is not valid. Please verify with whitelisted terms.";
      $response = ['jsonrpc' => $jsonrpc, 'result' => null, 'error' => ['code' => 32602, 'message' => $error_message], 'id' => $id];
      echo json_encode($response);

    }

  }

}

// Execute method if function exists
if ( $_SERVER['REQUEST_METHOD'] == 'POST' && function_exists($method) ) {
  return $method();
} elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' && ! function_exists($method) ) {
  $error_message = 'Sorry. The included method does not exist.';
  $response = ['jsonrpc' => $jsonrpc, 'result' => null, 'error' => ['code' => 32601, 'message' => $error_message], 'id' => $id];
  echo json_encode($response);
}