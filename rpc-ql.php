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

// Remote API function
function rpc_ql()
{

  global $jsonrpc;
  global $method;
  global $params;
  global $id;
  
  // Table and column fields dictionary
  $dictionary = [['Table' => 'persons',
                'Fields' => ['person_id' => 'integer - ID number of the person',
                  'person_name' => 'string - Name of the person',
                  'person_gender' => 'string - Male or Female',
                  'person_birth_date' => 'string - format YYYY-MM-DD']],
                ['Table' => 'places',
                'Fields' => ['place_id' => 'integer - ID number of the place',
                  'place_name' => 'string - Name of the place',
                  'place_state' => 'string - State where the place is located']]];

  // Show dictionary with GET request.
  if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) echo json_encode($dictionary);

  // Access API functionality with POST request.
  if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {

    // Require parameters based on JSON-RPC 2.0 and SQL
    if ( empty($jsonrpc) || empty($method) || empty($params['token']) || empty($params['query']) || empty($id) ) exit('Please set "jsonrpc", "method", "token" and "query" parameters, and request "ID".');
    $token = $params['token'];
    $query = $params['query'];
    
    // Token validation - token should be 12345
    if ( ! hash_equals( hash('sha256', 12345), hash('sha256', $token) ) ) exit('Token authentication failed');
    
    // Query validation - query should be alphanumeric with a few accepted characters and blacklisted SQL commands.
    if ( preg_match('/^[a-zA-Z0-9 _,*=()\']+$/i', $query) && ! preg_match('/(drop|insert|into|update)/i', $query) ) {

      // Table and column fields converter
      $mod_query = str_replace( 'persons', 'db_persons', $query ); // Persons table; Initial conversion of $query.
      $mod_query = str_replace( 'person_id', 'db_person_id', $mod_query ); // Succeeding conversion of $mod_query.
      $mod_query = str_replace( 'person_name', 'db_person_name', $mod_query );
      $mod_query = str_replace( 'person_gender', 'db_person_gender', $mod_query );
      $mod_query = str_replace( 'person_birth_date', 'db_person_birthdate', $mod_query );
      $mod_query = str_replace( 'places', 'db_places', $mod_query ); // Places table
      $mod_query = str_replace( 'place_id', 'db_place_id', $mod_query );
      $mod_query = str_replace( 'place_name', 'db_place_name', $mod_query );
      $mod_query = str_replace( 'place_state', 'db_place_state', $mod_query );

      // Set $error to null if $mod_query contains a query.
      if ( $mod_query !== false ) $error = null;

      $response = ['jsonrpc' => $jsonrpc, 'result' => $mod_query, 'error' => $error, 'id' => $id];
      echo json_encode($response);

    } else {

      // Failed validation
      $error_message = "The query is not valid. It should only contain alphanumeric characters, spaces, '_', ',', '*', '=', '(', ')' and '''. Only SELECT statements are allowed.";
      $response = ['jsonrpc' => $jsonrpc, 'result' => null, 'error' => ['code' => 32600, 'message' => $error_message], 'id' => $id];
      echo json_encode($response);

    }

  }

}

// Execute method if function exists
if ( function_exists($method) ) {
  return $method();
} else {
  $error_message = 'Sorry. The included method does not exist.';
  $response = ['jsonrpc' => $jsonrpc, 'result' => null, 'error' => ['code' => 32602, 'message' => $error_message], 'id' => $id];
  echo json_encode($response);
}