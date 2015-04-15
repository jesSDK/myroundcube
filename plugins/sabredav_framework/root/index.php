<?php
/**
 * This server combines both CardDAV and CalDAV functionality into a single
 * server. It is assumed that the server runs at the root of a HTTP domain (be
 * that a domainname-based vhost or a specific TCP port.
 */

$version = '1.13';

if (!defined('INSTALL_PATH')) {
    define('INSTALL_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
}

require_once 'vendor/autoload.php';

if (version_compare(Sabre\DAV\Version::VERSION, '2.1.0', '<')) {
    header('HTTP/1.0 503 Service Unavailable');
    die('Service Unavailable');
}

if  (isset($_GET['getpatches'])) {
    @include 'display_errors.inc.php';
}

$include_path = INSTALL_PATH . 'PEAR' . PATH_SEPARATOR;
$include_path.= ini_get('include_path');

if (set_include_path($include_path) === false) {
    die("Fatal error: ini_set/set_include_path does not work.");
}

/**
 * UTC or GMT is easy to work with, and usually recommended for any
 * application.
 */
date_default_timezone_set('UTC');

/***************************************************
 *
 * Configuration
 *
 ***************************************************/

@include 'config.inc.php';

/***************************************************
 *
 * SabreDAV Server Script
 *
 ***************************************************/
 
if (isset($_SERVER['REDIRECT_REDIRECT_REQUEST_METHOD'])){ 
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REDIRECT_REDIRECT_REQUEST_METHOD'];
}

$request = explode('/', $_SERVER['REQUEST_URI']);
if (isset($request[1])) {
    $request = $request[1];
} else {
    $request = '/';
}
$pdo = new PDO($dbtype . ':host=' . $dbhost . ';port=' . $dbport . ';dbname=' . $dbname, $dbuser, $dbpass);
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

/**
 * Mapping PHP errors to exceptions.
 *
 * While this is not strictly needed, it makes a lot of sense to do so. If an
 * E_NOTICE or anything appears in your code, this allows SabreDAV to intercept
 * the issue and send a proper response back to the client (HTTP/1.1 500).
 */
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");

// MyRoundcube sharing
include 'index.inc.php';

if($users_table == 'users'){
  if($authtype == 'imap'){
    $authBackend=new Sabre\DAV\Auth\Backend\ImapAuth($pdo, $imap_open, $autoban_interval, $autoban_attempts, $autoban_db_table, $authentication_success_logfile, $authentication_failure_logfile);
  }
  else if($authtype == 'basic'){
    $authBackend = new Sabre\DAV\Auth\Backend\BasicAuth($pdo, $users_table, $realm);
  }
  else{
    $authBackend = new Sabre\DAV\Auth\Backend\PDO($pdo, $users_table);
  }
}
else{
  if($authtype == 'basic' || $authtype == 'imap'){
    $authBackend = new Sabre\DAV\Auth\Backend\BasicAuth($pdo, $users_table, $realm);
  }
  else{
    $authBackend = new Sabre\DAV\Auth\Backend\PDO($pdo, $users_table);
  }
}
$principalBackend = new Sabre\DAVACL\PrincipalBackend\PDO($pdo);

if($users_table == 'users'){
  $carddavBackend = new Sabre\CardDAV\Backend\PDO($pdo);
}
else{
  $carddavBackend = new Sabre\CardDAV\Backend\Shared($pdo);
}
if($users_table == 'users'){
  $caldavBackend = new Sabre\CalDAV\Backend\PDO($pdo);
}
else{
  $caldavBackend = new Sabre\CalDAV\Backend\Shared($pdo);
}

// Directory structure
$nodes = [
    new Sabre\CalDAV\Principal\Collection($principalBackend),
    new Sabre\CalDAV\CalendarRootNode($principalBackend, $caldavBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
];

$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri($baseUri);
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend, $realm));
$server->addPlugin(new Sabre\DAVACL\Plugin());

if (isset($http_interface) && $http_interface > 0 && $users_table == 'users') {
    switch ($http_interface) {
        case 1:
            if (isset($user) && strtolower($user) == strtolower($http_admin)) {
                $server->addPlugin(new Sabre\DAV\Browser\Plugin());
                $browser = true;
            }
            break;
        case 2:
            if (isset($uri_parts) && (count($uri_parts) > 2 && isset($user)) || (isset($user) && strtolower($user) == strtolower($http_admin))) {
                $server->addPlugin(new Sabre\DAV\Browser\Plugin());
            }
            break;
    }
}

$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\CalDAV\Subscriptions\Plugin());
$server->addPlugin(new Sabre\CalDAV\Schedule\Plugin());
if (isset($user) && isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'myroundcube') === false) {
  $server->addPlugin(new Sabre\CalDAV\Schedule\IMipPluginAuth($user));
}
$server->addPlugin(new Sabre\CardDAV\Plugin());
$server->addPlugin(new Sabre\DAVACL\Plugin());
$server->addPlugin(new Sabre\CalDAV\ICSExportPlugin());
$server->exec();

if(isset($action) && isset($file) && isset($object) && isset($object_table) && isset($object_field) && is_string($notify) && is_string($lang)){
    $query = 'SELECT * FROM ' . $object_table . ' WHERE uri=? LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array($file));
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    $new_object = $row[$object_field];
    postBack($rcurl, $user, $lang, $notify, $action, $object, $new_object, $file, $type);
}

?>