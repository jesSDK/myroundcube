<?PHP
function slashify($str) {
    return unslashify($str).'/';
}

function unslashify($str){
    return preg_replace('/\/+$/', '', $str);
}

function https_check() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
        return true;
    }
}

function sendmail($to, $subject, $body, array $headers, $debug = false) { // called from ./vendor/sabre/dav/lib/CalDAV/Schedule/IMipPluginAuth.php
    global $smtpauth;

    $crlf = "\r\n";
    if ($debug === true) {
        echo '<textarea rows="12" cols="82" style="resize: none">';
    }
    if (is_array($smtpauth) && isset($smtpauth['user']) && isset($smtpauth['pass']) && $smtpauth['user'] !== false && $smtpauth['pass'] !== false) {
        set_error_handler(function() { /* ignore errors */ });
        require_once 'PEAR/Mail/Mail.php';
        require_once 'PEAR/Mail/mime.php';
        
        $hdrs = array();
        
        foreach ($headers as $header) {
          $temp = explode(':', $header, 2);
          $hdrs[trim($temp[0])] = trim($temp[1]);
        }

        $hdrs['Subject'] = $subject;

        $mime = new Mail_mime($crlf);

        $headers = $mime->headers($hdrs);
        
        $mail = Mail::factory('smtp',
            array (
                'host'     => $smtpauth['host'],
                'port'     => $smtpauth['port'],
                'auth'     => true,
                'debug'    => $debug,
                'username' => $smtpauth['user'],
                'password' => $smtpauth['pass'],
            )
        );
        try {
            $success = $mail->send($to, $headers, $body);
        }
        catch (Exception $e) {
            $success = false;
        }
        restore_error_handler();
    }
    else {
         $success = mail($to, $subject, $body, implode($crlf, $headers));
         if ($debug) {
             echo 'Sending test mail by PHP mail function ' . ($success ? 'succeeded.' : 'failed. Check PHP configuration and SMTP logs.');
         }
    }
    if ($debug === true) {
        echo '</textarea><br />';
    }
    return $success;
}

function postBack($url, $user, $lang, $notify, $action, $old_object, $new_object, $file, $type) {
    if ($url) {
        $url .= '?_action=plugin.sabredav_notify';
        $fields = array(
            '_user' => urlencode($user),
            '_notify' => urlencode($notify),
            '_lang' => urlencode($lang),
            '_dav' => urlencode($action),
            '_old_object' => urlencode($old_object),
            '_new_object' => urlencode($new_object),
            '_file' => urlencode($file),
            '_type' => urlencode($type),
            '_url' => urlencode('http' . (https_check() ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])
        );
        $fields_string = '';
        foreach ($fields as $key => $value) { 
            $fields_string .= $key . '=' . $value . '&';
        }
        rtrim($fields_string, '&');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_exec($ch); 
        curl_close($ch);
    }
}

function getDigest($authtype) {
    $digest = false;
    if ($authtype == 'basic') {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $digest = $_SERVER['PHP_AUTH_USER'];
        }
    }
    else {
        if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
            $digest = $_SERVER['PHP_AUTH_DIGEST'];
        }
        else if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
            if (strpos(strtolower($_SERVER['HTTP_AUTHENTICATION']),'digest') === 0) {
                $digest = substr($_SERVER['HTTP_AUTHENTICATION'], 7);
             }
        }
    }
    return $digest;
}

function digestParse($digest, $authtype) {
    if ($authtype == 'basic') {
        return $digest ? array('username' => $digest) : false;
    }
    else {
        $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
        $data = array();
        preg_match_all('@(\w+)=(?:(?:")([^"]+)"|([^\s,$]+))@', $digest, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $data[$m[1]] = $m[2] ? $m[2] : $m[3];
            unset($needed_parts[$m[1]]);
        }
        return $needed_parts ? false : $data;
    }
}

function digestVerify($realm, $A1, $authtype) {
    if ($authtype == 'basic') {
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="' . $realm . '"');
            header('HTTP/1.1 401 Unauthorized');
            echo 'AUTHORIZATION FAILED';
            die();
        }
        return $A1 === md5($_SERVER['PHP_AUTH_USER'] . ':' . $realm . ':' . $_SERVER['PHP_AUTH_PW']);
    }
    else {
        $nonce = uniqid();
        $digest = getDigest($authtype);
        if(!$digest){
            header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . $nonce . '",opaque="' . md5($realm) . '"');
            header('HTTP/1.1 401 Unauthorized');
            echo 'AUTHORIZATION FAILED';
            die();
        }
        $digestParts = digestParse($digest, $authtype);
        $A2 = md5("{$_SERVER['REQUEST_METHOD']}:{$digestParts['uri']}");
        $validResponse = md5("{$A1}:{$digestParts['nonce']}:{$digestParts['nc']}:{$digestParts['cnonce']}:{$digestParts['qop']}:{$A2}");
        return $digestParts['response'] === $validResponse;
    }
}

$user = false;
$resource = false;
$users_table = 'users';
$action = false;

$uri = preg_replace('/\/+$/', '', $_SERVER['REQUEST_URI']);
$uri_parts= explode('/', urldecode($uri));
if(isset($uri_parts[$resource_pos])){
    $resource = $uri_parts[$resource_pos];
}
if (isset($uri_parts[1]) && $uri_parts[1] == 'urlrewrite') {
    echo 'ok';
    exit;
}

if (isset($uri_parts[1]) && $uri_parts[1] == 'sendmail') {
    echo '<body style="margin:0; padding:0; font-family: \'Lucida Grande\',Verdana,Arial,Helvetica,sans-serif;font-size: 11px;">';
    if (isset($testsendmail) && $testsendmail === true) {
        if (!isset($smtpauth) || !isset($testrecipient)) {
            echo '<h4><br /><font color="red">Sending iMip invitations is not configured. SabreDAV framework configuation is missing <font color="black" style="font-weight:normal;font-size:10px"><i>$smtpauth</i> and/or <i>$testrecipient</i></font> configuration variables.</font></h4>';
        }
        else {
            $to = $testrecipient;
            $subject = 'SabreDAV IMip Test (MyRoundcube sabredav_framework)';
            $body = 'SabreDAV IMip Test (MyRoundcube sabredav_framework)';
            $headers = array(
                'From: ' . $to,
                'To: ' . $to,
            );
            $success = sendmail($to, $subject, $body, $headers, true);
            if ($success === true) {
                echo '<h3><font color="green">Sending test mail succeeded.</font></h3><font color="red"><h4>Please disable sending test mail <i><font color="black" style="font-weight:normal;font-size:10px">- $testsendmail = false; -</font></i> in SabreDAV Framework configuration now.</font></h4>';
            }
            else {
                echo '<font color="red">Sending test mail failed.</font>';
            }
        }
    }
    else {
      echo '<h3><font color="green">Sending test mail is disabled <i><font color="black" style="font-weight:normal;font-size:10px">- $testsendmail = false; -</font></i> in SabreDAV Framework configuration.</font></he>';
    }
    echo '</body>';
    exit;
}

$digest = getDigest($authtype);
$digestParts = digestParse($digest, $authtype);

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $user = str_replace('%40', '@', $user);
    $user = strtolower($_SERVER['PHP_AUTH_USER']);
    $temp = explode('@', $user, 2);
}
else if (is_array($digestParts) && isset($digestParts['username'])) {
    $user = str_replace('%40', '@', $user);
    $user = strtolower($digestParts['username']);
    $temp = explode('@', $user, 2);
}
else {
    if (isset($temp[$user_pos])) {
        $user = strtolower($temp[$user_pos]);
        $user = str_replace('%40', '@', $user);
        $temp = $user;
        $temp = explode('@', $temp, 2);
    }
    else {
        unset($temp);
    }
}

if (is_array($uri_parts) && count($uri_parts) == 1 && empty($_GET) && isset($_SERVER['HTTP_HOST'])) {
   $redirect = 'http' . (https_check() ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/.well-known';
   header('HTTP/1.1 301 Moved Permanently');
   header('Location: ' . $redirect);
   exit;
}
if (isset($uri_parts[1]) && $uri_parts[1] == '.well-known') {
  if (isset($uri_parts[2])) {
    switch (strtolower($uri_parts[2])) {
      case 'caldav':
        $redirect = 'http' . (https_check() ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/calendars/' . $user;
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        break;
      case 'carddav':
        $redirect = 'http' . (https_check() ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/addressbooks/' . $user;
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        break;
    }
  }
  else{
    $redirect = 'http' . (https_check() ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/principals/' . $user;
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
  }
  exit;
}

$auth = explode('/', urldecode($uri));
if(isset($auth[1]) && $auth[1] == 'checkAuth'){
  if($authtype == 'database'){
    echo 'error-outdated-' . $authtype;
    exit;
  }
  else if($authtype == 'imap'){
    $force = 'basic';
  }
  else{
    $force = $authtype;
  }
  digestVerify($realm, 0, $force);
  if($authtype == 'digest'){
    if(isset($_SERVER['PHP_AUTH_DIGEST'])){
      echo 'ok-digest';
    }
    else{
      echo 'error-digest';
    }
  }
  else{
    if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])){
      if($authtype == 'imap'){
        if(imap_open($imap_open, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], OP_HALFOPEN, 0)){
          echo 'ok-imap';
        }
        else{
          echo 'error-imap-' . $imap_open;
        }
      }
      else{
        echo 'ok-basic';
      }
    }
    else{
      echo 'error-basic';
    }
  }
  exit;
}

if (isset($temp[1])) {
    $user_domain = $temp[1];
}
else {
    $user_domain = 'default';
}

if (isset($map) && is_array($map)) {
    foreach ($map as $key => $val) {
        if ($key == $user_domain) {
            $rcurl = $val;
            break;
        }
    }
}
if (count($_GET) > 0) {
    $query = explode('?', $uri, 2);
    if (strpos($uri, '&') !== false) {
        $query = explode('&', $query[1]);
    }
    $uri = strtolower($query[0]);
    $query = explode('=', $query[count($query) - 1]);
    if ($query[0] == 'issabredav') {
        header("HTTP/1.1 200 OK");
        $port = $dbport != 3306 ? (':' . $dbport) : '';
        $hash = md5("$dbtype://$dbuser:$dbpass@$dbhost$port/$dbname");
        die('SabreDAV:' . $hash);
    }
    else if ($query[0] == 'getversion') {
      echo Sabre\DAV\Version::VERSION;
      exit;
    }
    else if ($query[0] == 'getpatches') {
        $check = '';
        if (is_dir(INSTALL_PATH . 'vendor/sabre/dav/lib')) {
            $check .= 'ok';
        }
        else {
            $check .= 'error';
        }
        if (
              file_exists(INSTALL_PATH . 'vendor/sabre/dav/lib/DAV/Auth/Backend/BasicAuth.php') &&
              file_exists(INSTALL_PATH . 'vendor/sabre/dav/lib/CalDAV/Backend/Shared.php') &&
              file_exists(INSTALL_PATH . 'vendor/sabre/dav/lib/CardDAV/Backend/Shared.php') &&
              file_exists(INSTALL_PATH . 'vendor/sabre/dav/lib/DAV/Auth/Backend/ImapAuth.php')
           ) {
            $check .= '|ok';
        }
        else {
            $check .= '|error';
        }
        if (file_exists(INSTALL_PATH . 'config.inc.php')) {
            $check .= '|ok';
        }
        else {
            $check .= '|error';
        }
        if (file_exists(INSTALL_PATH . 'display_errors.inc.php')) {
            $check .= '|ok';
        }
        else {
            $check .= '|error';
        }
        echo 'SabreDAV:' . $version . '|' . $check;
        exit;
    }
}

if ((isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['PHP_AUTH_DIGEST'])) && isset($_SERVER['HTTP_HOST'])) {
    $query = explode('.', strtolower($_SERVER['HTTP_HOST']));
    $resource = explode('/', urldecode(unslashify(strtolower($_SERVER['REQUEST_URI']))));

    if (is_array($resource) && isset($resource[$dav_pos])) {
        $path = $resource[$dav_pos];
    }
    else {
        $path = '';
    }
    if (is_array($resource) && isset($resource[$resource_pos])) {
        $resource = $resource[$resource_pos];
    }
    else {
        $resource = '';
    }

    if ($query[0] == $readonly_subdomain || $query[0] == $readwrite_subdomain) {
        if ($query[0] == $readwrite_subdomain) {
            $accesslevel = 1;
        }
        else{
            $accesslevel = 2;
        }

        if ($path != '' && strtolower($resource) != 'inbox' && strtolower($resource) != 'outbox') {
            if ($user != false && !isset($_GET['sabreAction'])) {
                if ($accesslevel == 1) {
                    switch ($_SERVER['REQUEST_METHOD']) {
                        case 'DELETE':
                            if (isset($uri)) {
                                $file = explode('/', $uri);
                                $file = end($file);
                                $type = explode('.', $file);
                                $type = end($type);
                                $type = strtolower($type);
                                if ($type == 'ics' || $type == 'vcf') {
                                    switch ($type) {
                                        case 'ics':
                                            $object_table = 'calendarobjects';
                                            $object_field = 'calendardata';
                                            break;
                                        case 'vcf':
                                            $object_table = 'cards';
                                            $object_field = 'carddata';
                                            break;
                                    }
                                    $query = 'SELECT * FROM ' . $object_table . ' WHERE uri=? LIMIT 1';
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute(array($file));
                                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                                    if (is_array($row)) {
                                        $object = $row[$object_field];
                                        $action = 'delete';
                                    }
                                    break;
                                }
                                else {
                                    header("HTTP/1.1 403 Forbidden");
                                    exit;
                                }
                            }
                            else {
                                header("HTTP/1.1 403 Forbidden");
                                exit;
                            }
                        case 'PUT':
                            if (isset($uri)) {
                                $file = explode('/', $uri);
                                $file = end($file);
                                $type = explode('.', $file);
                                $type = end($type);
                                $type = strtolower($type);
                                if ($type == 'ics' || $type == 'vcf') {
                                    switch ($type) {
                                        case 'ics':
                                            $object_table = 'calendarobjects';
                                            $object_field = 'calendardata';
                                            break;
                                        case 'vcf':
                                            $object_table = 'cards';
                                            $object_field = 'carddata';
                                            break;
                                    }
                                    $query = 'SELECT * FROM ' . $object_table . ' WHERE uri=? LIMIT 1';
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute(array($file));
                                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                                    if (is_array($row)) {
                                        $object = $row[$object_field];
                                        $action = 'edit';
                                    }
                                    else {
                                        $object = false;
                                        $action = 'create';
                                    }
                                }
                                else {
                                    header("HTTP/1.1 403 Forbidden");
                                    exit;
                                }
                            }
                            else {
                                header("HTTP/1.1 403 Forbidden");
                                exit;
                            }
                            break;
                        case 'MKCOL':
                        case 'MKCALENDAR':
                        case 'MOVE':
                        case 'PROPPATCH':
                            header("HTTP/1.1 403 Forbidden");
                            exit;
                    }
                }
                else if ($accesslevel == 2) {
                    switch($_SERVER['REQUEST_METHOD']){
                        case 'MKCOL':
                        case 'MKCALENDAR':
                        case 'DELETE':
                        case 'MOVE':
                        case 'PUT':
                        case 'PROPPATCH':
                            header("HTTP/1.1 403 Forbidden");
                            exit;
                    }
                }
            }
            else if (isset($_GET['sabreAction'])) {
                $tables = array('users_rw', 'users_r');
                foreach ($tables as $table) {
                    $query = "SELECT * FROM " . $table . " WHERE username=? LIMIT 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute(array($user));
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($row)) {
                        $A1 = $row['digesta1'];
                        if (digestVerify($realm, $A1, $authtype) === true) {
                            $users_table = $table;
                            $path = false;
                            break;
                        }
                    }
                }
            }
            else {
                header("HTTP/1.1 403 Forbidden");
                die('FORBIDDEN');
            }
        }
        if ($accesslevel == 1) {
            $users_table = 'users_rw';
        }
        else {
            $users_table = 'users_r';
        }
        $notify = false;
        $lang = 'en_US';
        $query = "SELECT * FROM " . $users_table . " WHERE username=? LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute(array($user));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row) && !empty($row['notify'])) {
            $notify = $row['notify'];
        }
        if (is_array($row) && !empty($row['lang'])) {
            $lang = $row['lang'];
        }
    }
}
?>