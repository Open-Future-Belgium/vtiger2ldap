vtiger2ldap
===========

Vtiger to LDAP Contact Synchronisation

How to use Vtiger2Ldap
----------------------

The Vtiger2Ldap script can run on any server that can contact:

- Vtiger through the webservices API
- Your company LDAP server

To be able to run this you will need:

* php-cli
* HTTP_Client (pear install HTTP_Client)
* Zend Json (Included!)

1. Configure
------------

Open 'include/config.php' and configure it to your needs, you will need to specify at least:

- VTIGER_APIURL (The URL to your vtiger installation)
- VTIGER_USERNAME
- VTIGER_APIKEY (a.k.a. AccessKey)

You can get the accesskey by logging on to vtiger as the user you would like to use, going to 'My Preferences' and copying the "Access Key" field.

- LDAP_URI (IP or Hostname to your LDAP installation)
- LDAP_BIND_DN (An LDAP user account to bind to LDAP with)
- LDAP_BIND_PW (The LDAP bind password)

- LDAP_SEARCH_DN (Your base DN)

- LDAP_CONTACTS_OU (OU containing the contacts)
- LDAP_USERS_OU (OU containing the users)

- LDAP_UID_MIN (Minimum uidNumber for the synchronised contacts)
- LDAP_UID_MAX (Maximum uidNumber for the synchronised contacts)

Copy the included 'zarafa/ldap.propmap.cfg' to /etc/zarafa/ on your Zarafa server.
Make sure the propmap gets loaded in /etc/zarafa/ldap.cfg

2. Run
------

Run the script with:

cd /path/to/vtiger2ldap && /usr/bin/php /path/to/vtiger2ldap.php


3. Put it in cron
-----------------

If you want to update the LDAP tree periodically put the command to run
the script in crontab:

```
# This will run vtiger2ldap @ 7:00, 12:00 and 00:00, logging will go to /dev/null
0 7,12,0 * * * cd /path/to/vtiger2ldap && /usr/bin/php /path/to/vtiger2ldap/vtiger2ldap.php > /dev/null
```
