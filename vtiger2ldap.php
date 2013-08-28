<?php

/*
 * Open-Future vtiger2ldap
 * PHP Code that gets vtiger contacts using vtiger webservices API,
 * and imports them into an LDAP tree.
 *
 * For use with Zarafa, or other software that can read out LDAP data.
 *
 * For further information, and how to integrate with Zarafa, please see
 * the included README file.
 *
 * @AUTHOR Bert Deferme <bert@open-future.be> www.open-future.be
 * @LICENSE GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @VERSION 1.0RC1
 *
 */

/*
 * Do not edit anything in this file unless you know what you are doing.
 * Configuration is done in the 'include/config.php' file.
 *
 */

// Require the config.php
// Configured in this file: Vtiger Webservices Authentication information, LDAP information, E-mail information.
require_once("include/config.php");

// Requirements (HTTP_CLIENT, Zend framework(included))
require_once("include/requirements.php");

// Require Vtiger Webservices Auth Code
require_once("include/VtigerWebAuth.php");

// Authenticate to vtiger, and get the sessionid for usage
$vauth = new VtigerWebAuth();
if ($vauth->authed()) {
  $sessionid = $vauth->getSessionId();
} else {
  print $vauth->getErr();
}

// Initialise empty string to store contacts without accounts.
// Searching on account information (Company Name) when the Vtiger contact
// does not have an account makes this script crash. We now catch this,
// store contacts without accounts, and send an e-mail about these contacts.
$noAcc = "";

// Get the vtiger webservices API url from the constant (config.php)
$endpointUrl = VTIGER_APIURL;

// First: get total number of vtiger contacts
$query = "select count(*) from Contacts;";
$queryParam = urlencode($query);
$params =  "sessionName=$sessionid&operation=query&query=$queryParam";

// Perform the query
print "[vtiger2ldap] Searching for contacts in Vtiger...\n\n";
$httpc = new HTTP_CLIENT();
$httpc->get("$endpointUrl?$params");
$response = $httpc->currentResponse();
$jsonResponse = Zend_JSON::decode($response['body']);

// Error handling
if($jsonResponse['success']==false)
  //handle the failure case.
  die('query failed:'.$jsonResponse['errorMsg']);

// Variables used to limit searches to 30 results, this results in
// faster searches and less load on the Vtiger server.
$numContacts = $jsonResponse['result'][0]["count"];
$start = 0;
$limit = 30;
$end = $start + $limit;

// Get the vtiger webservices API url from the constant (config.php)
$endpointUrl = VTIGER_APIURL;

while ($end <= $numContacts) { // while we still need to process contacts

$end = $start + $limit;

// First: get all vtiger contacts with webservices API, limited by $start and $end

$query = "select * from Contacts limit $start, $limit;";
$queryParam = urlencode($query);
$params =  "sessionName=$sessionid&operation=query&query=$queryParam";

// Perform the query
print "[vtiger2ldap] Searching for contacts in Vtiger...\n\n";
$httpc = new HTTP_CLIENT();
$httpc->get("$endpointUrl?$params");
$response = $httpc->currentResponse();
$jsonResponse = Zend_JSON::decode($response['body']);

// Error handling
if($jsonResponse['success']==false)
  //handle the failure case.
  die('query failed:'.$jsonResponse['errorMsg']);

// Array with results
$retrievedObjects = $jsonResponse['result'];

// Loop trough all vtiger contacts, check if they exist in LDAP
// If they exist: modify
// If not: add

foreach ($retrievedObjects as $obj) {

  // Get the account ID for this contact, used for getting the Company info
  $accountId = $obj["account_id"];

  // If no account ID is set, this contact has no account, store it for e-mail and
  // skip this account!
  if ($accountId == "") { 
    // Log details for sending an email
    $name=trim($obj["firstname"])." ".trim($obj["lastname"]);
    $desc="(".$obj["email"]." / ".$obj["contact_no"].")";
    $noAcc .= $name ." ". $desc . "\n";
    print "[vtiger2ldap] Got contact ".$newContact["cn"].", without account, logging for email...\n";
    continue;
  }

  // Get company info from vtiger webservices API
  $query = "select accountname from Accounts where id='".$accountId."';";
  $queryParam = urlencode($query);

  $params =  "sessionName=$sessionid&operation=query&query=$queryParam";
  $httpc = new HTTP_CLIENT();
  $httpc->get("$endpointUrl?$params");
  $response = $httpc->currentResponse();
  $jsonResponse = Zend_JSON::decode($response['body']);

  // Error handling
  if($jsonResponse['success']==false)
    //handle the failure case.
    die('query failed:'.$jsonResponse['errorMsg']);

  // Array with results
  $retrievedAccounts = $jsonResponse['result'];

  // Setup connection parameters to LDAP, from constants in 'include/config.php'
  $ldap_url= LDAP_URI;
  $username= LDAP_BIND_DN;
  $password= LDAP_BIND_PW;

  $ds = ldap_connect($ldap_url);

  // Setup LDAP options
  ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
  ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

  // Do a bind login to LDAP
  $login = ldap_bind($ds, $username, $password);

  // Setup search Parameters for LDAP (searching for uidNumbers)
  // Needed to make sure we don't create contacts with uidNumbers that already exist
  $dn= LDAP_SEARCH_DN;
  $filter="(uidNumber=*)";
  $getthese=array("uidNumber");

  // Perform the search
  $sr=ldap_search($ds, $dn, $filter, $getthese);
  $ldapResults = ldap_get_entries($ds,$sr);

  // Generate an array with already used uidNumbers
  // within our contact uidNumbers range (10000-20000)
  $uidnumbers = array();

  foreach ($ldapResults as $entry) {
    if ($entry['uidnumber'][0] >= LDAP_UID_MIN && $entry['uidnumber'][0] <= LDAP_UID_MAX ) {
      array_push($uidnumbers,$entry['uidnumber'][0]);
    }
  }

  // Sort the uidNumbers, and get the latest one used
  sort($uidnumbers);
  $last=end($uidnumbers);

  // If there are no uidNumbers within this range yet, set the last uidNumber to be LDAP_UID_MIN - 1
  // Else, set the last uidNumber from the array
  if($last == "") {
    $lastuidNumber= LDAP_UID_MIN - 1;
  } else {
    $lastuidNumber=end($uidnumbers);
  }

  // Set the uidNumber to be used for this contact
  if (!isset($uidNumber)) {
    $uidNumber = $lastuidNumber+1;
  }

  // Create an array to hold the new contact's data,
  // and populate it with the values that exist
  $newContact = array();

  // The cn is firstname + lastname
  $newContact["cn"] = trim($obj["firstname"])." ".trim($obj["lastname"]);

  if (!empty($obj["email"])) {
    $newContact["mail"] = $obj["email"];
  }

  $newContact["sn"] = $obj["lastname"];
  $newContact["givenName"] = $obj["firstname"];

  if (!empty($obj["mailingcity"])) {
      $newContact["l"] = $obj["mailingcity"];
      $newContact["physicalDeliveryOfficeName"] = $obj["mailingcity"];
  }
  if (!empty($obj["title"])) {
    $newContact["title"] = $obj["title"];
  }
  if (!empty($obj["mailingstreet"])) {
    $newContact["street"] = $obj["mailingstreet"];
  }
  if (!empty($obj["mailingzip"])) {
    $newContact["postalCode"] = $obj["mailingzip"];
  }
  if (!empty($obj["department"])) {
    $newContact["departmentNumber"] = $obj["department"];
  } else {
    $newContact["departmentNumber"] = $retrievedAccounts[0]["accountname"];
  }
  if (!empty($obj["mobile"])) {
    $newContact["mobile"] = $obj["mobile"];
  }
  if (!empty($obj["phone"])) {
    $newContact["telephoneNumber"] = $obj["phone"];
  }
  if (!empty($obj["otherphone"])) {
    $newContact["pager"] = $obj["otherphone"];
  }
  if (!empty($obj["fax"])) {
    $newContact["facsimileTelephoneNumber"] = $obj["fax"];
  }
  if (!empty($retrievedAccounts[0]["accountname"])) {
    $newContact["o"] = $retrievedAccounts[0]["accountname"];
  }

  // Set the objectClasses for this contact needed for Zarafa
  $newContact["objectClass"][0] = "zarafa-contact";
  $newContact["objectClass"][1] = "inetorgperson";
  $newContact["objectClass"][2] = "organizationalPerson";

  // Set uidNumber, uid and DN 
  $newContact["uidNumber"] = $uidNumber;
  $newContact["uid"] = $obj["firstname"]." ".$obj["lastname"]; 
  $myDn = 'cn='.$newContact["cn"].','.LDAP_CONTACTS_OU;

  print "[vtiger2ldap] Got contact ".$newContact["cn"].", proceeding with add/modify...\n";

  // Check if contact already exists (search for the CN, and get the uidNumber)
  $dn= LDAP_CONTACTS_OU;
  $filter='(cn='.$newContact["cn"].')';
  $getthese=array("uidNumber");

  // Perform the search
  $sr=ldap_search($ds, $dn, $filter, $getthese);

  $contactArray = ldap_get_entries($ds,$sr);

  // If there are no results, the contact does not exist yet -> we can add it...
  if ($contactArray["count"] == "0") {

    // ! IMPORTANT !
    // Check if there are no users in the LDAP tree under ou=users
    // Why? -> If BOTH a zarafa-user and zarafa-contact exist with the same
    // e-mail address Zarafa will STOP DELIVERING mails for that user.

    // If mail = set
    if (!empty($newContact["mail"])) {
      // Set search variables
      $dn= LDAP_USERS_OU;
      $filter='(mail='.$newContact["mail"].')';
      $getthese=array("mail");
      
      // Perform the search
      $sre=ldap_search($ds, $dn, $filter, $getthese);

      // Get the results
     $userArray = ldap_get_entries($ds,$sre);

     // If results are found, the e-mail exists on a user already -> for now: SKIP!
     if ($userArray["count"] > 0) {
       print "[vtiger2ldap] Was going to add $myDn but: e-mail found for zarafa-user! Skipping...\n\n";
       continue;
     }
   }
   
   // If we got here, there is no email set OR it does not exist on a user already
   // -> Add the contact in LDAP
   var_dump($newContact);
   $r = ldap_add($ds, $myDn, $newContact);

   // The contact was added, raise the uidNumber for the next contact found.
    $uidNumber++;
    print "[vtiger2ldap] Contact $myDn does not exist yet, adding...\n\n";
  } else {
    // Results were found, the contact exists already, use ldap_modify to modify the contact.
    // unset the uidNumber so we don't overwrite it!
    unset($newContact["uidNumber"]);
    // Modify the contact
    $r = ldap_modify($ds, $myDn, $newContact);
    print "[vtiger2ldap] Contact $myDn exists, modifying...\n\n";
  }
}
$start = $start + $limit;
}

// If contacts without an account are found, send an e-mail to the responsible person.
if ($noAcc != "") {
    print "[vtiger2ldap] Contacts without an account found, sending email...\n\n";
    $to= WARNMAIL_TO;
    $subject= WARNMAIL_SUBJECT;
    $body="Found vtiger contacts without an account during the last sync!\nThey will not be synced!\nPlease add them to an account!\n\nThe following contacts where not synced because they don't have an account:\n\n$noAcc";
    mail($to,$subject,$body);
}
