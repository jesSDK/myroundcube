<?php
/* This driver supports (user_get must be supported) */
function driver_get_backend(){
  return array('user_create', 'user_edit', 'user_delete');
}

function user_get_backend($username, $authenticate){
  if(class_exists('COM')){
    $username = strtolower(trim($username['arg']));
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    try{
      $obApp = new COM('hMailServer.Application');
      $obApp->Authenticate($authenticate['admin'], $authenticate['password']);
      $obDomain = $obApp->Domains->ItemByName($domain);
      $obAccount = $obDomain->Accounts->ItemByAddress($username);
      $props = array(
        'username' => $obAccount->Address,
        'active' => $obAccount->Active ? 1 : 0,
        'mailboxsize' => $obAccount->MaxSize, // 0 = unlimited
        'quota' => $obAccount->QuotaUsed ? $obAccount->QuotaUsed : 0, // false = not supported
        'abort' => false, // false = success (user found)
      );
    }
    catch(Exception $e){
      $props = array('abort' => true);
    }
  }
  else{
    $props = array('abort' => true);
  }
  return $props;
}

function user_create_backend($props, $authenticate){
  if(class_exists('COM')){
    $username = strtolower(trim($props['post']['_email'][0]));
    if($username){
      $domain = explode('@', $username);
      $domain = strtolower($domain[1]);
      $p = user_get_backend(array('arg' => $username), $authenticate);
      if($p['abort']){
        try{
          $obApp = new COM('hMailServer.Application');
          $obApp->Authenticate($authenticate['admin'], $authenticate['password']);
          $obDomain = $obApp->Domains->ItemByName($domain);
          $obAccount = $obDomain->Accounts->Add;
          $obAccount->Address = $username;
          $obAccount->Password = trim($props['post']['_pass']);
          $obAccount->Active = $props['post']['_active'] ? true : false;
          $obAccount->MaxSize = $props['post']['_size'] ? trim($props['post']['_size']) : 0;
          $obAccount->Save;
          $props = user_get_backend(array('arg' => $username), $authenticate);
        }
        catch(Exception $e){
          $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
        }
      }
      else{
        $props['post']['_user'] = $username;
        $props = user_edit_backend($props, $authenticate);
      }
    }
    else{
      $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.usernotfound'));
    }
  }
  else{
    $props = array('abort' => true, 'cancel' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.COMerror'));
  }
  return $props;
}

function user_edit_backend($props, $authenticate){
  if(class_exists('COM')){
    $username = strtolower(trim($props['post']['_user']));
    if(!$username){
      $username = strtolower(trim($props['post']['_email'][0]));
    }
    if($username){
      $domain = explode('@', $username);
      $domain = strtolower($domain[1]);
      $p = user_get_backend(array('arg' => $username), $authenticate);
      if(!$p['abort']){
        try{
          $obApp = new COM('hMailServer.Application');
          $obApp->Authenticate($authenticate['admin'], $authenticate['password']);
          $obDomain = $obApp->Domains->ItemByName($domain);
          $obAccount = $obDomain->Accounts->ItemByAddress($username);
          $password = trim($props['post']['_pass']);
          if($password && strtolower($password) != '<< encrypted >>'){
            $obAccount->Password = $password;
          }
          $obAccount->Active = $props['post']['_active'] ? true : false;
          $obAccount->MaxSize = $props['post']['_size'] ? trim($props['post']['_size']) : 0;
          $obAccount->Save;
          $props = user_get_backend(array('arg' => $username), $authenticate);
        }
        catch(Exception $e){
          $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
        }
      }
      else{
        $props['post']['_email'][0] = $username;
        $props = user_create_backend($props, $authenticate);
      }
    }
    else{
      $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.usernotfound'));
    }
  }
  else{
    $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.COMerror'));
  }
  return $props;
}

function user_delete_backend($props, $authenticate){
  if(class_exists('COM')){
    $username = strtolower(trim($props['username']));
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    $p = user_get_backend(array('arg' => $username), $authenticate);
    if(!$p['abort']){
      try{
        $obApp = new COM('hMailServer.Application');
        $obApp->Authenticate($authenticate['admin'], $authenticate['password']);
        $obDomain = $obApp->Domains->ItemByName($domain);
        $obAccount = $obDomain->Accounts->ItemByAddress($username);
        $obDomain->Accounts->DeleteByDBID($obAccount->ID);
        $p = user_get_backend(array('arg' => $username), $authenticate);
        if($p['abort']){
          $props = array('abort' => false);
        }
        else{
          $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
        }
      }
      catch(Exception $e){
        $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'errorsaving'));
      }
    }
    else{
      $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.usernotfound'));
    }
  }
  else{
    $props = array('abort' => true, 'message' => array('type' => 'error', 'label' => 'companyaddressbook.COMerror'));
  }
  return $props;
}
?>