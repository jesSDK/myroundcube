<?php
/****************************
 *
 * Driver framework
 *
 ****************************/

/* This driver supports (user_get must be supported) */
function driver_get_backend(){
  return array('user_create', 'user_edit', 'user_delete');
}
 
/* Fetch user properties */
function user_get_backend($username, $authenticate){
  if(1 == 1){ // system checks
    $username = strtolower(trim($username['arg']));
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    try{ // get the user's properties
      $props = array(
        'username' => $username,
        'active' => 1, // 1 = active, 0 = incactive,
        'mailboxsize' => 5, // 0 = unlimited
        'quota' => 25, // false = not supported, else integer (percent)
        'isalias' => false, // true or false
        'abort' => false, // false = success (user found)
      );
    }
    catch(Exception $e){
      $props = array('abort' => true); // an error occured when fetching properties
    }
  }
  else{
    $props = array('abort' => true); // system checks failed
  }
  return $props;
}

/* Create a new user */
function user_create_backend($props, $authenticate){
  if(1 == 1){ // system checks
    $username = strtolower(trim($props['post']['_email'][0]));
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    $p = user_get_backend(array('arg' => $username), $authenticate);
    if($p['abort']){ // the user does not exist
      try{ // create the user
        $props = user_get_backend(array('arg' => $username), $authenticate); // fetch properties
      }
      catch(Exception $e){
        $props = array('abort' => true, 'cancel' => true); // an error occured when creating the user
      }
    }
    else{
      $props = user_edit_backend($props, $authenticate); // the user already exists ... overwrite properties
    }
  }
  else{
    $props = array('abort' => true, 'cancel' => true); // system checks failed
  }
  return $props;
}

/* Edit an existing user */
function user_edit_backend($props, $authenticate){
  if(1 == 1){ // system checks
    $username = strtolower(trim($props['post']['_user']));
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    $p = user_get_backend(array('arg' => $username), $authenticate);
    if(!$p['abort']){ // the user exists
      try{ // edit properties
        $props = user_get_backend(array('arg' => $username), $authenticate); // fetch properties
      }
      catch(Exception $e){
        $props = array('abort' => true); // an error occured when editing the user
      }
    }
    else{
      $props = user_create_backend($props, $authenticate); // the user does not exist ... create the user
    }
  }
  else{
    $props = array('abort' => true); // system checks failed
  }
  return $props;
}

/* Delete a user */
function user_delete_backend($props, $authenticate){
  if(1 == 1){ // system checks
    $username = strtolower(trim($props['username']));
    $domain = explode('@', $username);
    $domain = strtolower($domain[1]);
    $p = user_get_backend(array('arg' => $username), $authenticate);
    if(!$p['abort']){ // the user exists
      try{ // delete the user
        $p = user_get_backend(array('arg' => $username), $authenticate);
        if($p['abort']){ // the user does not exist
          $props = array('abort' => false);
        }
        else{
          $props = array('abort' => true); // deletion failed
        }
      }
      catch(Exception $e){
        $props = array('abort' => true); // an error occured when deleting the user
      }
    }
    else{
      $props = array('abort' => false); // the user does not exist
    }
  }
  else{
    $props = array('abort' => true); // system checks failed
  }
  return $props;
}
?>