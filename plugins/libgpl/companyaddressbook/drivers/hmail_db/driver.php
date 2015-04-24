<?php
/****************************
 *
 * Driver framework
 *
 ****************************/
 
define('HMAIL_DB_VERSION', '5400');

/* This driver supports (user_get must be supported) */
function driver_get_backend(){
  return array('user_create', 'user_edit');
}
 
function hmail_db_connect(){
  $rcmail = rcube::get_instance();
  if($dsn = $rcmail->config->get('companyaddressbook_db_dsnw')){
    $db = new rcube_db($dsn, '', FALSE);
    $db->set_debug((bool)$rcmail->config->get('sql_debug'));
    $db->db_connect('w');
    $sql = 'SELECT * FROM hm_dbversion LIMIT 1';
    $result = $db->query($sql);
    if($db->error){
      return false;
    }
    $v = $db->fetch_assoc($result);
    if($v['value'] == HMAIL_DB_VERSION){
      return $db;
    }
    else{
      return false;
    }
  }
  else{
    return false;
  }
}

/* Fetch user properties */
function user_get_backend($username, $authenticate, $db = false){
  if(!$db){
    $db = hmail_db_connect();
  }
  $username = $username['arg'];
  if($username && $db){
    $isalias = false;
    $temp = explode('@', $username);
    $user = $temp[0];
    $domain = $temp[1];
    $sql = 'SELECT * FROM hm_domain_aliases WHERE daalias LIKE ? LIMIT 1';
    $result = $db->query($sql, $domain);
    $daalias = $db->fetch_assoc($result);
    if($daalias['dadomainid']){
      $sql = 'SELECT * FROM hm_domains WHERE domainid=? LIMIT 1';
      $result = $db->query($sql, $daalias['dadomainid']);
      $domain = $db->fetch_assoc($result);
      if($domain['domainname']){
        $domain = $domain['domainname'];
        $isalias = true;
      }
      else{
        return array('abort' => true, 'db' => $db);
      }
    }
    $sql = 'SELECT * FROM hm_aliases WHERE aliasname LIKE ? LIMIT 1';
    $result = $db->query($sql, $user . '@' . $domain);
    $alias = $db->fetch_assoc($result);
    if($alias['aliasvalue']){
      $username = $alias['aliasvalue'];
      $isalias = true;
      $temp = explode('@', $username);
      $user = $temp[0];
      $domain = $temp[1];
      $username = $user . '@' . $domain;
    }
    $sql = 'SELECT * FROM hm_accounts WHERE accountaddress LIKE ? LIMIT 1';
    $result = $db->query($sql, $username);
    $user = $db->fetch_assoc($result);
    if($user){
      $props = array(
        'id' => $user['accountid'],
        'username' => $user['accountaddress'],
        'active' => (int) $user['accountactive'],
        'mailboxsize' => (int) $user['accountmaxsize'],
        'isalias' => $isalias,
        'quota' => false,
        'abort' => false,
        'db' => $db,
      );
    }
    else{
      $props = array('abort' => true, 'db' => $db);
    }
  }
  else{
    $props = array('abort' => true, 'db' => false);
  }
  return $props;
}
 
/* Create a new user */
function user_create_backend($props, $authenticate, $db = false){
  $username = strtolower(trim($props['post']['_email'][0]));
  if($username){
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    $p = user_get_backend(array('arg' => $username), $authenticate, $db);
    $db = $p['db'];
    if($p['abort']){
      try{
        if($db){
          $mailboxsize = (int) trim($props['post']['_size']);
          $pass = trim($props['post']['_pass']);
          $active = (int) trim($props['post']['_active']);
          $sql = 'SELECT * FROM hm_domains WHERE domainname LIKE ? LIMIT 1';
          $result = $db->query($sql, $domain);
          $domainid = $db->fetch_assoc($result);
          if($domainid = $domainid['domainid']){
            $sql = "INSERT INTO hm_accounts ".
              "(" . 
                "accountdomainid,".
                "accountaddress,".
                "accountpassword,".
                "accountactive,".
                "accountisad,".
                "accountmaxsize,".
                "accountpwencryption,".
                "accountadminlevel,".
                "accountaddomain,".
                "accountadusername,".
                "accountvacationmessageon,".
                "accountvacationmessage,".
                "accountvacationsubject,".
                "accountforwardenabled,".
                "accountforwardaddress,".
                "accountforwardkeeporiginal,".
                "accountenablesignature,".
                "accountsignatureplaintext,".
                "accountsignaturehtml,".
                "accountlastlogontime,".
                "accountvacationexpires,".
                "accountvacationexpiredate,".
                "accountpersonfirstname,".
                "accountpersonlastname" . 
             ") VALUES (?, ?, ? , ?, '0', ?, '2', '0', '', '', '0', '', '', '0', '', '0', '0', '', '', '" . date('Y-m-d H:i:s',time()) . "', '0', '" . date('Y-m-d H:i:s',time()) . "', '', '')";
            $result = $db->query($sql, $domainid, $username, md5($pass), $active, $mailboxsize);
            $user_insert_id = $db->insert_id();
            if($user_insert_id){
              $sql = "SELECT * FROM hm_imapfolders WHERE folderaccountid=?";
              $result = $db->query($sql, $user_insert_id);
              $folders = $db->fetch_assoc($result);
              if(!$folders){
                $sql = "INSERT INTO hm_imapfolders " .
                    "(" .
                      "folderaccountid,".
                      "folderparentid,".
                      "foldername,".
                      "folderissubscribed,".
                      "foldercreationtime,".
                      "foldercurrentuid".
                    ") VALUES (?, '-1', 'INBOX' , '1', ?, '0')";
                $result = $db->query($sql, $user_insert_id, date('Y-m-d H:i:s',time()));
                $sql = "SELECT * FROM hm_imapfolders WHERE folderaccountid=? AND foldername=? LIMIT 1";
                $result = $db->query($sql, $user_insert_id, 'INBOX');
                $folder_insert_id = $db->fetch_assoc($result);
                $folder_insert_id = $folder_insert_id['folderaccountid'];
                if($folder_insert_id){
                  $props = user_get_backend(array('arg' => $username), $authenticate, $db);
                }
                else{
                  $sql = "DELETE FROM hm_accounts WHERE accountid=? LIMIT 1";
                  $db->query($sql, $user_insert_id);
                  $sql = "DELETE FROM hm_imapfolders WHERE folderaccountid=?";
                  $db->query($sql, $user_insert_id);
                  $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
                }
              }
              else{
                $sql = "DELETE FROM hm_accounts WHERE accountid=? LIMIT 1";
                $db->query($sql, $user_insert_id);
                $sql = "DELETE FROM hm_imapfolders WHERE folderaccountid=?";
                $db->query($sql, $user_insert_id);
                $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
              }
            }
            else{
              $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
            }
          }
          else{
            $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
          }
        }
        else{
          $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.DBerror'));
        }
      }
      catch(Exception $e){
        $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
      }
    }
    else{
      if(!$p['isalias']){
        $props['post']['_user'] = $username;
        $props = user_edit_backend($props, $authenticate, $db);
      }
      else{
        $props = array('abort' => true, 'cancel' => $p['islias'], 'message' => array('type' => 'error', 'label' => 'companyaddressbook.isalias'));
      }
    }
  }
  else{
    $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
  }
  return $props;
}

/* Edit an existing user */
function user_edit_backend($props, $authenticate, $db = false){
  $username = strtolower(trim($props['post']['_user']));
  if(!$username){
    $username = strtolower(trim($props['post']['_email'][0]));
  }
  if($username){
    $p = user_get_backend(array('arg' => $username), $authenticate, $db);
    $db = $p['db'];
    if($db){
      if(!$p['abort']){
        try{
          $mailboxsize = (int) trim($props['post']['_size']);
          $pass = trim($props['post']['_pass']);
          $active = (int) trim($props['post']['_active']);
          if(strtolower($pass) == '<< encrypted >>'){
            $sql = 'UPDATE hm_accounts SET accountactive=?, accountmaxsize=? WHERE accountaddress LIKE ? LIMIT 1';
            $db->query($sql, $active, $mailboxsize, $username);
          }
          else{
            $sql = 'UPDATE hm_accounts SET accountpassword=?, accountpwencryption=?, accountactive=?, accountmaxsize=? WHERE accountaddress LIKE ? LIMIT 1';
            $db->query($sql, md5($pass), '2', $active, $mailboxsize, $username);
          }
          $props = user_get_backend(array('arg' => $username), $authenticate, $db);
        }
        catch(Exception $e){
          $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
        }
      }
      else{
        $props['post']['_email'][0] = $username;
        $props = user_create_backend($props, $authenticate, $db);
      }
    }
    else{
      $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.DBerror'));
    }
  }
  else{
    $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
  }
  return $props;
}

/* Delete a user */
function user_delete_backend($props, $authenticate, $db = false){
  // We don't have access to filesystem
  return array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.notimplemented'));
}
?>