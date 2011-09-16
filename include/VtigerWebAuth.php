<?php
/**
 * Vtiger Webservices API - authentication module
 */

class VtigerWebAuth {

  protected $userName = VTIGER_USERNAME;
  protected $accessKey = VTIGER_ACCESSKEY;

  public $sessionId;
  public $authed;
  public $errMsg;
  
  function __construct() {
    $this->_doLogin();
  }

  public function getSessionId() {
    return $this->sessionId;
  }

  public function authed() {
    return $this->authed;
  }

  public function getErr() {
    return $this->errMsg;
  }

  private function _doLogin() {

    $httpc = new HTTP_CLIENT();
    $vtigerUrl = VTIGER_APIURL;
    $httpc->get("$vtigerUrl?operation=getchallenge&username=".$this->userName);
    $response = $httpc->currentResponse();
    $jsonResponse = Zend_JSON::decode($response['body']);

    if($jsonResponse['success']==false) {
      $this->authed = false;
      $this->errMsg = "getchallenge failed:".$jsonResponse['error']['errorMsg'];
    }
    else {
    
      $challengeToken = $jsonResponse['result']['token'];
      $generatedKey = md5($challengeToken.$this->accessKey);

      $httpc->post("$vtigerUrl",
      array('operation'=>'login', 'username'=>$this->userName,
          'accessKey'=>$generatedKey), true);
      $response = $httpc->currentResponse();

      $jsonResponse = Zend_JSON::decode($response['body']);

      if($jsonResponse['success']==false) {
        $this->authed = false;
	$this->errMsg = "login failed:".$jsonResponse['error']['code']." (".$jsonResponse['error']['message'].")";
      } else {
 	$this->authed = true;
        $this->sessionId = $jsonResponse['result']['sessionName'];
	$this->userId = $jsonResponse['result']['userId'];
      }
    }
  }

}

?>
