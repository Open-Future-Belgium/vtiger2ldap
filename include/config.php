<?php
/*
 * Open-Future vtiger2ldap
 * PHP Code that gets vtiger contacts,
 * encodes them as LDIF data, and imports
 * into an LDAP tree.
 *
 * For use with Zarafa, or other software that can read out LDAP data.
 *
 * @AUTHOR Bert Deferme <bert@open-future.be> www.open-future.be
 * @LICENSE GPLv3
 * http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

/*
 * Vtiger Webservices API - Configuration
 */

define('VTIGER_APIURL', 'http://vtiger.company.com/webservice.php'); // Vtiger API URL
define('VTIGER_USERNAME', 'admin'); // Vtiger Username
define('VTIGER_ACCESSKEY', '<accesskey>'); // Vtiger APIkey

/*
 * LDAP - Configuration
 */

define('LDAP_URI','127.0.0.1'); // LDAP URI
define('LDAP_BIND_DN','uid=binder,ou=system-accounts,dc=company'); // LDAP BIND DN
define('LDAP_BIND_PW','<pw>'); // LDAP BIND PASSWORD

define('LDAP_SEARCH_DN','dc=company'); // LDAP SEARCH DN
                                       // Input the Base DN here

define('LDAP_CONTACTS_OU','ou=contacts,dc=company'); // LDAP CONTACTS OU
                                                     // INPUT The DN of the OU where contacts are stored here

define('LDAP_USERS_OU','ou=users,dc=company'); // LDAP USERS OU

define('LDAP_UID_MIN','10000'); // LDAP Minimum uidNumber to be used by Contacts
define('LDAP_UID_MAX','20000'); // LDAP Maximum uidNumber to be used by Contacts

/*
 * Warning Email Configuration
 * This is an e-mail that is sent when users without an account exist
 * in Vtiger. Normally this should never happen, but it can happen and it
 * could break the script without this check.
 */

define('WARNMAIL_TO','someone@company.com');
define('WARNMAIL_SUBJECT','[vtiger2ldap] Vtiger contacts without account spotted');

// Correct include path to include the ZEND minimal framework.

$path = getcwd()."/include/zend";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

?>
