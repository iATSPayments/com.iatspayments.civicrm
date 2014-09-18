<?php

class CRM_iATS_Page_json {
  function run() {
    // generate json output from iats service calls
    $request = $_POST;
    $request = $_REQUEST;
    foreach(array('reset','q','IDS_request_uri','IDS_user_agent') as $key) {
      if (isset($request[$key])) {
        unset($request[$key]);
      }
    }
    $options = array();
    foreach(array('type','method','iats_domain') as $key) {
      if (isset($request[$key])) {
        $options[$key] = $request[$key];
        unset($request[$key]);
      }
    }
    $credentials = array();
    foreach(array('agentCode','password') as $key) {
      if (isset($request[$key])) {
        $credentials[$key] = $request[$key];
        unset($request[$key]);
      }
    }
   
    // testing hacks
    $request['beginDate'] = date('c',strtotime('+12 days'));
    $request['endDate'] = date('c',strtotime('+43 days'));
    $request['firstName'] = 'Fred'; 
    $request['lastName'] = 'Flintstone'; 
    $request['address'] = '123 Rubble Way';
    $request['city'] = 'Bedrock';
    $request['country'] = 'Great Britain';
    $request['email'] = 'fred@civicrm.ca';
    $request['zipCode'] = 'A1234';
    $request['ACHEFTReferenceNum'] = '';
    $request['companyName'] = '';
    $request['accountCustomerName'] = $request['firstName'].' '.$request['lastName'];
    $request['accountNum'] = '00000012345678';
    // use the iATSService object for interacting with iATS
    require_once("CRM/iATS/iATSService.php");
    $iats = new iATS_Service_Request($options);
    $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
    // make the soap request
    $response = $iats->request($credentials,$request);
    // process the soap response into a readable result
    if (!empty($response)) {
      $result = $iats->result($response);
    }
    else {
      $result = array('Invalid request');
      $result = array_merge($credentials,$request);
    }
    header('Content-Type: text/javascript');
    echo json_encode(array_merge($result));
    exit;
  }
}
