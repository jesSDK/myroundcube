<?php

require_once (dirname(__FILE__).'/../../../tasklist/drivers/database/tasklist_database_driver.php');
require_once (dirname(__FILE__).'/../../../calendar/drivers/calendar_driver.php');
require_once (dirname(__FILE__).'/../../../calendar/drivers/database/database_driver.php');
require_once (dirname(__FILE__).'/../../../calendar/drivers/caldav/caldav_driver.php');
require_once (dirname(__FILE__).'/../../../libgpl/encryption/encryption.php');
require_once (dirname(__FILE__).'/../../../libgpl/caldav/caldav_sync.php');

class tasklist_caldav_driver extends tasklist_database_driver
{
    const OBJ_TYPE_VCAL = "vcal";
    const OBJ_TYPE_VTODO = "vtodo";
    
    public $undelete = false;
    public $sortable = false;
    public $attachments = false;
    public $alarms = true;
    public $alarm_types = array('DISPLAY', 'EMAIL');

    private $rc;
    private $plugin;
    private $events;
    private $updates;
    private $current_cal_id;
    
    private $db_lists = 'calendars';
    private $db_lists_caldav_props = 'calendars_caldav_props';
    private $db_tasks = 'vtodo';
    private $db_tasks_caldav_props ='vtodo_caldav_props';

    private $sync_clients = array();
    private $crypt_key;
    
    static private $debug = null;

    // Min. time period to wait until sync check.
    private $sync_period = 10; // seconds

    /**
     * Default constructor
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);
        $this->rc = $plugin->rc;
        
        $this->plugin = $plugin;

        // read database config
        $db = $this->rc->get_dbh();
        $this->db_lists = $this->rc->config->get('db_table_lists', $db->table_name($this->db_lists));
        $this->db_lists_caldav_props = $this->rc->config->get('db_table_lists_caldav_props', $db->table_name($this->db_lists_caldav_props));
        $this->db_tasks = $this->rc->config->get('db_table_tasks', $db->table_name($this->db_tasks));
        $this->db_tasks_caldav_props = $this->rc->config->get('db_table_tasks_caldav_props', $db->table_name($this->db_tasks_caldav_props));

        $this->crypt_key = $this->rc->config->get("calendar_crypt_key", "%E`c{2;<J2F^4_&._BxfQ<5Pf3qv!m{e");

        $this->_init_sync_clients();
    }

    /**
     * Helper method to log debug msg if debug mode is enabled.
     */
    static public function debug_log($msg)
    {
      if (self::$debug === true)
        rcmail::console(__CLASS__.': '.$msg);
    }

    /**
     * Initializes calendar sync clients.
     *
     * @param array $cal_ids Optional list of calendar ids. If empty, caldav_driver::list_calendars()
     *              will be used to retrieve a list of calendars.
     */
    private function _init_sync_clients($cal_ids = array())
    {
        if(sizeof($cal_ids) == 0) $cal_ids = array_keys($this->get_lists());

        foreach($cal_ids as $cal_id)
        {
            $props = $this->_get_caldav_props($cal_id, self::OBJ_TYPE_VCAL);
            if($props !== false) {
                $this->_expand_pass($props);
                self::debug_log("Initialize sync client for calendar ".$cal_id);
                $sslverify = array(
                  $this->rc->config->get('calendar_curl_verify_peer', true),
                  $this->rc->config->get('calendar_curl_verify_host', true)
                );
                $this->sync_clients[$cal_id] = new caldav_sync($cal_id, $props, $sslverify, 'tasklist_caldav_driver');
            }
        }
    }

    /**
     * Performs caldav updates on given tasks.
     *
     * @param array Caldav and event properties to update. See caldav_sync::get_updates().
     * @return array List of event ids.
     */
    public function perform_updates($updates, $callback = true)
    {
        $task_ids = array();
        
        $num_created = 0;
        $num_updated = 0;
        foreach($updates as $update)
        {
            if($update['remote_event']['_type'] == 'task')
            {
                // local event -> update event
                if(isset($update["local_event"]))
                {
                    $local_task = (array)$update["local_event"];
                    unset($local_task["attachments"]);
                    $task = is_array($update["remote_event"]) ? ($update["remote_event"] + $local_task) : $local_task;
                    $task['id'] = isset($task['task_id']) ? $task['task_id'] : $task['id'];
                    $task['complete'] = $task['complete'] > 1 ? ((int)$task['complete'] / 100) : 0;
                    $task['tags'] = $update['remote_event']['categories'];
                    $task['flagged'] = $update['remote_event']['priority'] ? intval($update['remote_event']['priority']) : 0;
                    $task['list'] = $update['remote_event']['calendar'];
                    if($update['remote_event']['start'])
                    {
                        $start = $update['remote_event']['start']->format('Y-m-d H:i');
                        $start = explode(' ', $start);
                        $task['startdate'] = $start[0];
                        $task['starttime'] = $start[1];
                    }
                    if($update['remote_event']['due'])
                    {
                        $due = $update['remote_event']['due']->format('Y-m-d H:i');
                        $due = explode(' ', $due);
                        $task['date'] = $due[0];
                        $task['time'] = $due[1];
                    }
                    if(parent::edit_task($task))
                    {
                        $task_id = $task['id'];
                        self::debug_log("Updated event \"$task_id\".");

                        $props = array(
                            "url" => $update["url"],
                            "tag" => $update["etag"]
                        );

                        $this->_set_caldav_props($task_id, self::OBJ_TYPE_VTODO, $props);
                        array_push($task_ids, $task_id);
                        $num_updated ++;
                    }
                    else
                    {
                        self::debug_log("Could not perform event update: ".print_r($update, true));
                    }
                }

                // no local event -> create event
                else
                {
                    $update['remote_event']['complete'] = $update['remote_event']['complete'] > 1 ? ($update['remote_event']['complete'] / 100) : 0;
                    $update['remote_event']['tags'] = $update['remote_event']['categories'];
                    $update['remote_event']['flagged'] = $update['remote_event']['priority'] ? intval($update['remote_event']['priority']) : 0;
                    $update['remote_event']['list'] = $update['remote_event']['calendar'];
                    if($update['remote_event']['start'])
                    {
                        $start = $update['remote_event']['start']->format('Y-m-d H:i');
                        $start = explode(' ', $start);
                        $update['remote_event']['startdate'] = $start[0];
                        $updete['remote_event']['starttime'] = $start[1];
                    }
                    if($update['remote_event']['due'])
                    {
                        $due = $update['remote_event']['due']->format('Y-m-d H:i');
                        $due = explode(' ', $due);
                        $update['remote_event']['date'] = $due[0];
                        $updete['remote_event']['time'] = $due[1];
                    }
                    
                    
                    $task_id = parent::create_task($update['remote_event']);

                    if($task_id)
                    {
                        self::debug_log("Created event \"$task_id\".");

                        $props = array(
                            "url" => $update["url"],
                            "tag" => $update["etag"]
                        );

                        $this->_set_caldav_props($task_id, self::OBJ_TYPE_VTODO, $props);
                        array_push($task_ids, $task_id);
                        $num_created ++;
                    }
                    else
                    {
                        self::debug_log("Could not perform event creation: ".print_r($update, true));
                    }
                }
            }
        }
        
        foreach($updates as $update)
        {
            if($update['remote_event']['_type'] == 'task' && isset($update['remote_event']['parent_id']))
            {
                $uid = $update['remote_event']['parent_id'];
                $related_to = false;
                
                if(!$parent_task = parent::get_task($uid))
                {
                  $temp = explode('-', $uid);
                  $related_to = '-' . end($temp);
                  $uid = str_replace($related_to, '', $update['remote_event']['parent_id']);
                  $parent_task = parent::get_task($uid);
                }
                
                if(is_array($parent_task))
                {
                    $task = parent::get_task($update['remote_event']['uid']);
                    if(is_array($task) && $parent_task['id'] != $task['id'])
                    {
                        $task['parent_id'] = $parent_task['id'] . ($related_to ? ('-' . $related_to) : '');
                        parent::edit_task($task);
                    }
                }
            }
        }

        self::debug_log("Created $num_created new tasks, updated $num_updated task.");
        
        if($callback && $this->rc->action == 'refresh')
        {
            $this->events = new caldav_driver($this->plugin, false);

            foreach($updates as $idx => $update)
            {
                if($local_event = $this->events->get_event($update['remote_event']['uid']))
                {
                    $updates[$idx]['local_event'] = $local_event;
                }
            }
            
            $updated_event_ids = $this->events->perform_updates($updates, false);
            if(is_array($this->updates))
            {
                list($this->updates, $synced_event_ids) = $this->updates;
                $events = array();
                foreach($this->events->load_all_events($this->current_cal_id) as $event)
                {
                    if($event["recurrence_id"] == 0)
                    {
                        array_push($events, $event);
                    }
                }
                foreach($events as $event)
                {
                    if(array_search($event['id'], $updated_event_ids) === false && // No updated event
                        array_search($event['id'], $synced_event_ids) === false) // No in-sync event
                    {
                        // Assume: Event not in sync and not updated, so delete!
                        $this->events->remove_event($event, true);
                        self::debug_log("Remove event \"" . $event['id'] . "\".");
                    }
                }

            }
        }

        return $task_ids;
    }

    /**
     * Return all tasks from the given calendar.
     *
     * @param int Calendar id.
     * @return array
     */
    public function load_all_tasks($cal_id)
    {
        $result = $this->rc->db->query(
            "SELECT * FROM " . $this->db_tasks .
            " WHERE tasklist_id = ?", $cal_id);
        $tasks = array();
        while($result && ($task = $this->rc->db->fetch_assoc($result))) {
            $task['id'] = $task['task_id'];
            array_push($tasks, $task);
        }
        return $tasks;
    }

    /**
     * Synchronizes tasks of given calendar.
     *
     * @param int Calendar id.
     */
    private function _sync_calendar($cal_id)
    {
        self::debug_log("Syncing calendar id \"$cal_id\".");
        
        $this->current_cal_id = $cal_id;
        $cal_sync = $this->sync_clients[$cal_id];
        $tasks = array();
        $caldav_props = array();
        // Ignore recurrence events and read caldav props
        foreach($this->load_all_tasks($cal_id) as $task)
        {
            if($task['recurrence_id'] == 0)
            {
                array_push($tasks, $task);
                array_push($caldav_props,
                    $this->_get_caldav_props($task["task_id"], self::OBJ_TYPE_VTODO));
            }
        }
        $updates = $cal_sync->get_updates($tasks, $caldav_props);

        if($updates)
        {
            $this->updates = $updates;
            list($updates, $synced_task_ids) = $updates;
            $updated_task_ids = $this->perform_updates($updates);
            // Delete events that are not in sync or updated.
            foreach($tasks as $task)
            {
                if(array_search($task['task_id'], $updated_task_ids) === false && // No updated task
                    array_search($task['task_id'], $synced_task_ids) === false) // No in-sync task
                {
                    // Assume: Task not in sync and not updated, so delete!
                    $task['id'] = $task['task_id'];
                    parent::delete_task($task, true);
                    self::debug_log("Remove task \"" . $task['id'] . "\".");
                }
            }
           
            // Update calendar ctag ...
            $cal_props = $this->_get_caldav_props($cal_id, self::OBJ_TYPE_VCAL);
            $cal_props["tag"] = $cal_sync->get_ctag();
            $this->_set_caldav_props($cal_id, self::OBJ_TYPE_VCAL, $cal_props);
        }

        self::debug_log("Successfully synced calendar id \"$cal_id\".");
    }
    
    /**
     * Determines whether the given calendar is in sync regarding
     * calendar's ctag and the configured sync period.
     *
     * @param int Calender id.
     * @return boolean True if calendar is in sync, true otherwise.
     */
    private function _is_synced($cal_id)
    {
        $now = date(self::DB_DATE_FORMAT);
        $last = date(self::DB_DATE_FORMAT, time() - $this->sync_clients[$cal_id]->sync * 60);

        // Atomic sql: Check for exceeded sync period and update last_change.
        $query = $this->rc->db->query(
            "UPDATE " . $this->db_lists_caldav_props ." " .
            "SET last_change = ? " .
            "WHERE obj_id = ? AND obj_type = ? " .
            "AND last_change <= ?",
            $now, $cal_id, self::OBJ_TYPE_VCAL, $last);

        if($query->rowCount() > 0)
        {
            $is_synced = $this->sync_clients[$cal_id]->is_synced();
            self::debug_log("Calendar \"$cal_id\" ".($is_synced ? "is in sync" : "needs update").".");
            return $is_synced;
        }
        else
        {
            self::debug_log("Sync period active: Assuming calendar \"$cal_id\" to be in sync.");
            return true;
        }
    }

     /**
     * Expand all "%p" occurrences in 'pass' element of calendar object 
     * properties array with RC (imap) password. 
     * Other elements are left untouched.
     * 
     * @param array List of caldav properties
     *    url: Absolute calendar URL or relative event URL.
     *    tag: Calendar ctag or event etag.
     *    user: Authentication user in case of calendar obj.
     *    pass: Authentication password in case of calendar obj.
     *    last_change: Read-only DateTime obj of the last change.
     * 
     * @return array List of caldav properties, with expanded 'pass' element. Original array is modified too.
     *    url: Absolute calendar URL or relative event URL.
     *    tag: Calendar ctag or event etag.
     *    user: Authentication user in case of calendar obj.
     *    pass: Authentication password in case of calendar obj.
     *    last_change: Read-only DateTime obj of the last change.
     *      
     */
    private function _expand_pass(& $props)
    {
        if ($props !== false) {
            if (isset($props['pass'])){
                $props['pass'] = str_replace('%p', $this->rc->get_user_password(), $props['pass']);
            }
            if (stripos($props['url'], 'https://apidata.googleusercontent.com/caldav/v2/') === 0){
                $props['pass'] = '***TOKEN***';
            }
            return $props; 
        }    
        return false;
    }

    /**
     * Sets caldav properties.
     *
     * @param int $obj_id
     * @param int One of CALDAV_OBJ_TYPE_CAL, CALDAV_OBJ_TYPE_EVENT or CALDAV_OBJ_TYPE_TODO.
     * @param array List of caldav properties:
     *   url: Absolute calendar URL or relative event URL.
     *   tag: Calendar ctag or event etag.
     *  user: Authentication user in case of calendar obj.
     *  pass: Authentication password in case of calendar obj.
     *
     * @return True on success, false otherwise.
     */
    private function _set_caldav_props($obj_id, $obj_type, array $props)
    {
        // Don't set ctags - reserved for calendar
        if ($obj_type == 'vcal')
        {
            $db_table = $this->db_lists_caldav_props;
        }
        else
        {
            $db_table = $this->db_tasks_caldav_props;
        }
        
        $this->_remove_caldav_props($obj_id, $obj_type);
        
        $password = isset($props["pass"]) ? $props["pass"] : null;
        if ($password) {
            $e = new Encryption(MCRYPT_BlOWFISH, MCRYPT_MODE_CBC);
            $p = $e->encrypt($password, $this->crypt_key);
            $password = base64_encode($p);
        }

        $query = $this->rc->db->query(
            "INSERT INTO " . $db_table . " (obj_id, obj_type, url, tag, user, pass) ".
            "VALUES (?, ?, ?, ?, ?, ?)",
            $obj_id,
            $obj_type,
            $props["url"],
            isset($props["tag"]) ? $props["tag"] : null,
            isset($props["user"]) ? $props["user"] : null,
            $password);

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Gets caldav properties.
     *
     * @param int $obj_id
     * @param int One of CALDAV_OBJ_TYPE_CAL, CALDAV_OBJ_TYPE_EVENT or CALDAV_OBJ_TYPE_TODO.
     * @return array List of caldav properties or false on error:
     *    url: Absolute calendar URL or relative event URL.
     *    tag: Calendar ctag or event etag.
     *   user: Authentication user in case of calendar obj.
     *   pass: Authentication password in case of calendar obj.
     * last_change: Read-only DateTime obj of the last change.
     */
    private function _get_caldav_props($obj_id, $obj_type)
    {
        if ($obj_type == 'vcal')
        {
            $db_table = $this->db_lists_caldav_props;
        }
        else
        {
            $db_table = $this->db_tasks_caldav_props;
        }
        
        $result = $this->rc->db->query(
            "SELECT * FROM " . $db_table . " p ".
            "WHERE p.obj_type = ? AND p.obj_id = ? ", $obj_type, $obj_id);

        if ($result && ($prop = $this->rc->db->fetch_assoc($result)) !== false) {
            $password = isset($prop["pass"]) ? $prop["pass"] : null;
            if ($password) {
                $p = base64_decode($password);
                $e = new Encryption(MCRYPT_BlOWFISH, MCRYPT_MODE_CBC);
                $prop["pass"] = $e->decrypt($p, $this->crypt_key);
            }            
            return $prop;
        }

        return false;
    }

    /**
     * Removes caldav properties.
     *
     * @param int $obj_id
     * @param int One of CALDAV_OBJ_TYPE_CAL, CALDAV_OBJ_TYPE_EVENT or CALDAV_OBJ_TYPE_TODO.
     * @return True on success, false otherwise.
     */
    private function _remove_caldav_props($obj_id, $obj_type)
    {
        if ($obj_type == 'vcal')
        {
            $db_table = $this->db_lists_caldav_props;
        }
        else
        {
            $db_table = $this->db_tasks_caldav_props;
        }
        
        $query = $this->rc->db->query(
            "DELETE FROM " . $db_table . " ".
            "WHERE obj_type = ? AND obj_id = ? ", $obj_type, $obj_id);

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Get a list of available tasks lists from this source
     */
    public function get_lists($active = false)
    {
      return parent::get_lists($active);
    }

    /**
     * Create a new list assigned to the current user
     *
     * @param array Hash array with list properties
     * @return mixed ID of the new list on success, False on error
     * @see tasklist_driver::create_list()
     */
    public function create_list($prop)
    {
        return false;
    }

    /**
     * Update properties of an existing tasklist
     *
     * @param array Hash array with list properties
     * @return boolean True on success, Fales on failure
     * @see tasklist_driver::edit_list()
     */
    public function edit_list($prop)
    {
        $query = $this->rc->db->query(
            "UPDATE " . $this->db_lists . "
             SET   name=?, showalarms=?
             WHERE calendar_id=?
             AND   user_id=?",
            $prop['name'],
            $prop['showalarms']?1:0,
            $prop['id'],
            $this->rc->user->ID
        );

        return $this->rc->db->affected_rows($query);    }

    /**
     * Set active/subscribed state of a list
     *
     * @param array Hash array with list properties
     * @return boolean True on success, Fales on failure
     * @see tasklist_driver::subscribe_list()
     */
    public function subscribe_list($prop)
    {
      $query = $this->rc->db->query(
        "UPDATE " . $this->db_lists . "
         SET tasks=? WHERE calendar_id=?
         AND user_id=?",
        $prop['active'] ? 1 : 2, // 2 = unsubscribed
        $prop['id'],
        $this->rc->user->ID
      );
      
       return $this->rc->db->affected_rows($query);
    }

    /**
     * Delete the given list with all its contents
     *
     * @param array Hash array with list properties
     * @return boolean True on success, Fales on failure
     * @see tasklist_driver::remove_list()
     */
    public function remove_list($prop)
    {
        return false;
    }

    /**
     * Get number of tasks matching the given filter
     *
     * @param array List of lists to count tasks of
     * @return array Hash array with counts grouped by status (all|flagged|today|tomorrow|overdue|nodate)
     * @see tasklist_driver::count_tasks()
     */
    function count_tasks($lists = null)
    {
        return parent::count_tasks($lists);
    }

    /**
     * Get all taks records matching the given filter
     *
     * @param array Hash array wiht filter criterias
     * @param array List of lists to get tasks from
     * @param boolean Include clones of recurring tasks
     * @return array List of tasks records matchin the criteria
     * @see tasklist_driver::list_tasks()
     */
    function list_tasks($filter, $lists = null, $virtual = true)
    {
        foreach($this->sync_clients as $cal_id => $cal_sync) {
            if(!$this->_is_synced($cal_id))
                $this->_sync_calendar($cal_id);
        }

        return parent::list_tasks($filter, $lists);
    }

    /**
     * Return data of a specific task
     *
     * @param mixed  Hash array with task properties or task UID
     * @return array Hash array with task properties or false if not found
     */
    public function get_task($prop)
    {
        return parent::get_task($prop);
    }

    /**
     * Get all decendents of the given task record
     *
     * @param mixed  Hash array with task properties or task UID
     * @param boolean True if all childrens children should be fetched
     * @return array List of all child task IDs
     */
    public function get_childs($prop, $recursive = false)
    {
        return parent::get_childs($prop, $recursive);
    }

    /**
     * Get a list of pending alarms to be displayed to the user
     *
     * @param  integer Current time (unix timestamp)
     * @param  mixed   List of list IDs to show alarms for (either as array or comma-separated string)
     * @return array   A list of alarms, each encoded as hash array with task properties
     * @see tasklist_driver::pending_alarms()
     */
    public function pending_alarms($time, $lists = null)
    {
        return parent::pending_alarms($time, $lists);
    }

    /**
     * Feedback after showing/sending an alarm notification
     *
     * @see tasklist_driver::dismiss_alarm()
     */
    public function dismiss_alarm($task_id, $snooze = 0)
    {
        $success = parent::dismiss_alarm($task_id, $snooze);
        
        $task = parent::get_master(array('id' => $task_id));
        
        if(!$task['recurrence'] && $snooze > 0)
        {
            $success = false;
        
            $result = $this->rc->db->limitquery(
                "SELECT tasklist_id FROM " . $this->db_tasks . "
                WHERE task_id = ?",
                0,
                1,
                $task_id
            );
        
            $result = $this->rc->db->fetch_assoc($result);
            
            $tasklist_id = $result['tasklist_id'];

            if($this->sync_clients[$tasklist_id] && is_array($result))
            {
                $props = $this->_get_caldav_props($task_id, self::OBJ_TYPE_VTODO);
                $task['alarms'] = '@' . (time() + $snooze) . ':DISPLAY';
                $this->rc->db->query(
                    "UPDATE " . $this->db_tasks . "
                    SET alarms = ? WHERE task_id = ?",
                    $task['alarms'],
                    $task_id
                );
                
                $sync_client = $this->sync_clients[$tasklist_id];
                
                if(is_array($props))
                {
                    $task = $this->_save_preprocess($task);
                    $success = $sync_client->update_event($task, $props);
                }
                else
                {
                    $success = false;
                }
            }
            else
            {
                $success = $result ? true : false;
            }
        }
        
        return $success;

    }

    /**
     * Add a single task to the database
     *
     * @param array Hash array with task properties (see header of this file)
     * @return mixed New event ID on success, False on error
     * @see tasklist_driver::create_task()
     */
    public function create_task($task)
    {
        $cal_id = $task['list'];
        
        if(!is_array($task['tags'])){
          $task['tags'] = array(
            0 => $this->lists[$cal_id]['name'],
          );
        }

        $task_id = parent::create_task($task);
        
        if($task['isclone'])
        {
            return $task_id;
        }

        $task['_type'] = 'task';

        if($task_id !== false && isset($this->sync_clients[$cal_id]))
        {
            if($task['tags'])
            {
                $task['categories'] = $task['tags'];
            }
            if($task['startdate'])
            {
                $time = $task['startdate'];
                if($task['starttime'])
                {
                    $time .= ' ' . $task['starttime'] . ':00';
                }
                if(strtotime($time))
                {
                   $task['start'] = new DateTime($time);
                }
            }
            if($task['date'])
            {
                $time = $task['date'];
                if($task['time'])
                {
                    $time .= ' ' . $task['time'] . ':00';
                }
                if(strtotime($time))
                {
                   $task['due'] = new DateTime($time);
                }
            }
            if($task['parent_id'])
            {
                $parent_task = parent::get_task(array('id' => $task['parent_id']));
                $temp = explode('-', $task['parent_id']);
                if(count($temp) > 1)
                {
                    $ts = end($temp);
                }
                else
                {
                    $ts = false;
                }
                $task['parent_id'] = $parent_task['uid'] . ($ts ? ('-' . $ts) : '');
            }
            $sync_client = $this->sync_clients[$cal_id];
            $task = $this->_save_preprocess($task);
            $props = $sync_client->create_event($task);
            if($props === false)
            {
                self::debug_log("Unkown error while creating caldav task, undo creating local task \"$task_id\"!");
                $task['id'] = $task_id;
                parent::delete_task($task, true);
                return false;
            }
            else
            {
                self::debug_log("Successfully pushed task \"$task_id\" to caldav server.");
                $this->_set_caldav_props($task_id, self::OBJ_TYPE_VTODO, $props);

                // Trigger calendar sync to update ctags and etags.
                $this->_sync_calendar($cal_id);
                $this->_reload_list();
                return $task_id;
            }
        }
        else
        {
          $this->_reload_list();
          return $task_id ? $task_id : false;
        }
    }

    /**
     * Update an task entry with the given data
     *
     * @param array Hash array with task properties
     * @return boolean True on success, False on error
     * @see tasklist_driver::edit_task()
     */
    public function edit_task($task, $old_task = null)
    {
        if($task['_savemode'] == 'new')
        {
            $task['uid'] = $this->plugin->generate_uid();
            return $this->create_task($task);
        }
        else
        {
            $sync_enforced = ($old_task != null);
            $task_id = (int) $task['id'];
            $cal_id = $task['list'];

            if(isset($task['_fromlist']))
            {
                $delete = $task;
                $delete['list'] = $task['_fromlist'];
                if($success = $this->delete_task($delete))
                {
                    unset($task['id']);
                    unset($task['changed']);
                    unset($task['_fromlist']);
                    $task['raw'] = $task['title'];
                    foreach($task as $prop => $val)
                    {
                        if(!$val)
                            unset($task[$prop]);
                    }
                    return $this->create_task($task) ? true : false;
                }
                else
                {
                    return false;
                }
            }
            else
            {
                if($old_task == null)
                    $old_task = parent::get_master($task);

                if($success = parent::edit_task($task))
                {
                     if(isset($this->sync_clients[$cal_id]))
                     {
                        $success = $this->_edit_task($task_id, $cal_id);
                        if($success === true)
                        {
                            self::debug_log("Successfully updated task \"$task_id\".");

                            // Trigger calendar sync to update ctags and etags.
                            $this->_sync_calendar($cal_id);
                            $this->_reload_list($task, $old_task);
                            return true;
                        }
                        else if($success < 0 && $sync_enforced == false)
                        {
                            self::debug_log("Task \"$task_id\", tag \"" . $props['tag'] . "\" not up to date, will update calendar first ...");
                            $this->_sync_calendar($cal_id);
                            return $this->edit_task($task, $old_task); // Re-try after re-sync
                        }
                        else
                        {
                            self::debug_log("Unkown error while updating caldav task, undo updating local task \"$task_id\"!");
                            parent::edit_task($old_task);
                            return false;
                        }
                    }
                    else
                    {
                        return $success;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Sync edited task
     *
     * @param integer Task database identifier
     * @param integer Calendar database identifier
     * @return boolean success true or false
     */
    public function _edit_task($task_id, $cal_id)
    {
        // Get updates event and push to caldav.
        $task = parent::get_master(array('id' => $task_id));
        $task['priority'] = $task['flagged'] ? intval($task['flagged']) : 0;
        $task['_type'] = 'task';
        if($task['parent_id'])
        {
            $parent_task = parent::get_task(array('id' => $task['parent_id']));
            $temp = explode('-', $task['parent_id']);
            if(count($temp) > 1)
            {
                $ts = end($temp);
            }
            else
            {
                $ts = false;
            }
            $task['parent_id'] = $parent_task['uid'] . ($ts ? ('-' . $ts) : '');
        }
        if($task['tags'])
        {
            $task['categories'] = $task['tags'];
        }
        if($task['startdate'])
        {
            $time = $task['startdate'];
            if($task['starttime'])
            {
                $time .= ' ' . $task['starttime'] . ':00';
            }
            if(strtotime($time))
            {
                $task['start'] = new DateTime($time, $this->plugin->timezone);
            }
        }
        if($task['date'])
        {
            $time = $task['date'];
            if($task['time'])
            {
                $time .= ' ' . $task['time'] . ':00';
            }
            if(strtotime($time))
            {
                $task['due'] = new DateTime($time, $this->plugin->timezone);
            }
        }
        if(is_array($task['recurrence']['EXCEPTIONS']))
        {
            foreach($task['recurrence']['EXCEPTIONS'] as $idx => $exception)
            {
                if($exception['startdate'])
                {
                    $time = $exception['startdate'];
                    if($exception['starttime'])
                    {
                        $time .= ' ' . $exception['starttime'] . ':00';
                    }
                    if(strtotime($time))
                    {
                        $exception['start'] = new DateTime($time, $this->plugin->timezone);
                    }
                }
                if($exception['date'])
                {
                    $time = $exception['date'];
                    if($exception['time'])
                    {
                        $time .= ' ' . $exception['time'] . ':00';
                    }
                    if(strtotime($time))
                    {
                        $exception['due'] = new DateTime($time, $this->plugin->timezone);
                    }
                }
                $exception['_type'] = 'task';
                $exception['priority'] = $exception['flagged'] ? intval($exception['flagged']) : 0;
                $task['recurrence']['EXCEPTIONS'][$idx] = $exception;
            }
        }
        $sync_client = $this->sync_clients[$cal_id];
        $prop_id = $task['current']['recurrence_id'] ? (int)$task['current']['recurrence_id'] : $task_id;
        $props = $this->_get_caldav_props($prop_id, self::OBJ_TYPE_VTODO);

        if(is_array($props))
        {
            $task = $this->_save_preprocess($task);
            $success = $sync_client->update_event($task, $props);
        }
        else
        {
            $success = false;
        }
        
        return $success;
    }

    /**
     * Move a single task to another list
     *
     * @param array   Hash array with task properties:
     * @return boolean True on success, False on error
     * @see tasklist_driver::move_task()
     */
    public function move_task($prop)
    {
        return $this->edit_task($prop);
    }

    /**
     * Remove a single task from the database
     *
     * @param array   Hash array with task properties
     * @param boolean Remove record irreversible
     * @return boolean True on success, False on error
     * @see tasklist_driver::delete_task()
     */
    public function delete_task($prop, $force = true)
    {
      $prop = $this->_get_id($prop);
      $task = (array) parent::get_task($prop);
      $master = parent::get_master($prop);
      $task_id = (int) $task['id'];
      $cal_id = (int) ($prop['list'] ? $prop['list'] : $task['list']);
      $success = false;
      if ($prop['isclone'] && $prop['mode'] == 2) { // create EXCEPTION
        if ($success = parent::delete_task($prop, true)) {
          if ($props = $this->_get_caldav_props($task_id, self::OBJ_TYPE_VTODO)) {
            $success = $this->_edit_task($task_id, $cal_id);
          }
        }
      }
      else if (!$prop['isclone'] && $prop['mode'] == 3) { // delete parent and remove relation from subtasks
        if (isset($this->sync_clients[$cal_id])) {
          $sync_client = $this->sync_clients[$cal_id];
          $children = (array) parent::get_childs($prop, true);
          foreach ($children as $child_id) { // process childs
            $child_props = $this->_get_caldav_props($child_id, self::OBJ_TYPE_VTODO);
            if (is_array($child_props)) {
              if ($child = $this->get_master(array('id' => $child_id))) {
                unset($child['parent_id']);
                $child = $this->_save_preprocess($child);
                $sync_client->update_event($child, $child_props);
              }
            }
          }
          $props = $this->_get_caldav_props($task_id, self::OBJ_TYPE_VTODO);
          if ($success = parent::delete_task($prop, true)) {
            $success = $sync_client->remove_event($props);
          }
        }
        else {
          $success = parent::delete_task($prop, true);
        }
      }
      else if (!$prop['isclone'] && $prop['mode'] == 4) { // delete parent and subtasks
        if (isset($this->sync_clients[$cal_id])) {
          $sync_client = $this->sync_clients[$cal_id];
          $children = (array) parent::get_childs($prop, true);
          foreach ($children as $child_id) { // process childs
            $child_props = $this->_get_caldav_props($child_id, self::OBJ_TYPE_VTODO);
            if (is_array($child_props)) {
              $task = $this->_save_preprocess($child_props);
              $sync_client->remove_event($child_props);
            }
          }
          $props = $this->_get_caldav_props($task_id, self::OBJ_TYPE_VTODO);
          if ($success = parent::delete_task($prop, true)) { // process parent
            $success = $sync_client->remove_event($props);
          }
        }
        else {
          $success = parent::delete_task($prop, true);
        }
      }
      else {
        $props = $this->_get_caldav_props($task_id, self::OBJ_TYPE_VTODO);
        $props = $this->_get_caldav_props($task_id, self::OBJ_TYPE_VTODO);
        if ($success = parent::delete_task($task)) {
          if ($master['id'] != $prop['id']) { // delete EXCEPTION
            if (isset($this->sync_clients[$cal_id])) {
              $success = $this->_edit_task($master['id'], $cal_id);
            }
            $this->_reload_list();
          }
          else {
            if (is_array($props)) {
              if (isset($this->sync_clients[$cal_id])) {
                $sync_client = $this->sync_clients[$cal_id];
                $success = $sync_client->remove_event($props);
              }
            }
          }
        }
      }
      
      if ($success === true) {
        self::debug_log("Successfully removed task \"$task_id\".");
      }
      else {
        self::debug_log("Unkown error while removing caldav task \"$task_id\", force sync of calendar \"$task_id\"!");
      }
      
      // Trigger calendar sync to update ctags and etags.
      if (isset($this->sync_clients[$cal_id])) {
        $this->_sync_calendar($cal_id);
      }
      
      return $success;
    }

    /**
     * Restores a single deleted task (if supported)
     *
     * @param array Hash array with task properties
     * @return boolean True on success, False on error
     * @see tasklist_driver::undelete_task()
     */
    public function undelete_task($prop)
    {
        return parent::undelete_task($prop);
    }

    /**
     * Compute absolute time to notify the user
     */
    private function _get_notification($task)
    {
        if ($task['alarms'] && $task['complete'] < 1 || strpos($task['alarms'], '@') !== false) {
            $alarm = libcalendaring::get_next_alarm($task, 'task');

        if ($alarm['time'] && $alarm['action'] == 'DISPLAY')
          return date('Y-m-d H:i:s', $alarm['time']);
      }

      return null;
    }
    
    /**
     * Reload list
     */
    private function _reload_list($task = null, $old_task = null)
    {
        if ($this->rc->task == 'tasks')
        {
            $reload = true;
            if ($task && $old_task)
            {
                if ($task['status'] == 'COMPLETED' && $old_task['status'] != 'COMPLETED')
                {
                    $reload = false;
                }
            }
            if ($reload)
            {
                $this->rc->output->command('plugin.reload_data');
            }
        }
    }
    
    /**
     * Get real database identifier
     * @param array Hash array with task properties
     * @return array Hash array with task properties
     */
    private function _get_id($task)
    {
      if (isset($task['id'])) {
        if ($id = $task['id']) {
          $id = current(explode('-', $id));
          if (is_numeric($id)) {
            $task['id'] = $id;
          }
        }
      }
    
      return $task;
    }
    
    /**
     * Final task modifications before passing to CalDAV client
     *
     * @param array  task
     *
     * @return array modified task
     */
    private function _save_preprocess($task)
    {
      $task['_type'] = 'task';
      $tz = new DateTimezone('UTC');
      if ($task['start']) {
        if (is_array($task['start'])) {
          $task['start'] = new DateTime($task['start']['date'], new DateTimeZone($task['start']['timezone'] ? $task['start']['timezone'] : $this->plugin->timezone));
        }
        $task['start']->setTimezone($tz);
      }
      if ($task['due']) {
        if (is_array($task['due'])) {
          $task['due'] = new DateTime($task['due']['date'], new DateTimeZone($task['due']['timezone'] ? $task['due']['timezone'] : $this->plugin->timezone));
        }
        $task['due']->setTimezone($tz);
      }
      if ($task['recurrence_date']) {
        if (is_array($task['recurrence_date'])) {
          $task['recurrence_date'] = new DateTime($task['recurrence_date']['date'], new DateTimeZone($task['recurrence_date']['timezone'] ? $task['recurrence_date']['timezone'] : $this->plugin->timezone));
        }
        $task['recurrence_date'] = $task['recurrence_date']->setTimezone($tz);
      }
      if (is_array($task['recurrence']) && is_array($task['recurrence']['RDATE'])) {
        foreach ($task['recurrence']['RDATE'] as $idx => $rdate) {
          if (is_array($task['recurrence']['RDATE'][$idx])) {
            $task['recurrence']['RDATE'][$idx] = new DateTime($task['recurrence']['RDATE'][$idx]['date'], new DateTimeZone($task['recurrence']['RDATE'][$idx]['timezone'] ? $task['recurrence']['RDATE'][$idx]['timezone'] : $this->plugin->timezone));
          }
          $task['recurrence']['RDATE'][$idx] = $task['recurrence']['RDATE'][$idx]->setTimezone($tz);
        }
      }
      if (is_array($task['recurrence']) && is_array($task['recurrence']['EXCEPTIONS'])) {
        foreach ($task['recurrence']['EXCEPTIONS'] as $idx => $exception) {
          $task['recurrence']['EXCEPTIONS'][$idx]['_type'] = 'task';
          if ($task['recurrence']['EXCEPTIONS'][$idx]['created']) {
            if (is_array($task['recurrence']['EXCEPTIONS'][$idx]['created'])) {
              $task['recurrence']['EXCEPTIONS'][$idx]['created'] = new DateTime($task['recurrence']['EXCEPTIONS'][$idx]['created']['date'], new DateTimeZone($task['recurrence']['EXCEPTIONS'][$idx]['created']['timezone'] ? $task['recurrence']['EXCEPTIONS'][$idx]['created']['timezone'] : $this->plugin->timezone));
            }
            $task['recurrence']['EXCEPTIONS'][$idx]['created'] = $task['recurrence']['EXCEPTIONS'][$idx]['created']->setTimezone($tz);
          }
          if ($task['recurrence']['EXCEPTIONS'][$idx]['changed']) {
            if (is_array($task['recurrence']['EXCEPTIONS'][$idx]['changed'])) {
              $task['recurrence']['EXCEPTIONS'][$idx]['changed'] = new DateTime($task['recurrence']['EXCEPTIONS'][$idx]['changed']['date'], new DateTimeZone($task['recurrence']['EXCEPTIONS'][$idx]['changed']['timezone'] ? $task['recurrence']['EXCEPTIONS'][$idx]['changed']['timezone'] : $this->plugin->timezone));
            }
            $task['recurrence']['EXCEPTIONS'][$idx]['changed'] = $task['recurrence']['EXCEPTIONS'][$idx]['changed']->setTimezone($tz);
          }
          if ($task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date']) {
            if (is_array($task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date'])) {
              $task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date'] = new DateTime($task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date']['date'], new DateTimeZone($task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date']['timezone'] ? $task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date']['timezone'] : $this->plugin->timezone));
            }
            $task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date'] = $task['recurrence']['EXCEPTIONS'][$idx]['recurrence_date']->setTimezone($tz);
          }
          if ($task['recurrence']['EXCEPTIONS'][$idx]['start']) {
            if (is_array($task['recurrence']['EXCEPTIONS'][$idx]['start'])) {
              $task['recurrence']['EXCEPTIONS'][$idx]['start'] = new DateTime($task['recurrence']['EXCEPTIONS'][$idx]['start']['date'], new DateTimeZone($task['recurrence']['EXCEPTIONS'][$idx]['start']['timezone'] ? $task['recurrence']['EXCEPTIONS'][$idx]['start']['timezone'] : $this->plugin->timezone));
            }
            $task['recurrence']['EXCEPTIONS'][$idx]['start'] = $task['recurrence']['EXCEPTIONS'][$idx]['start']->setTimezone($tz);
          }
          if ($task['recurrence']['EXCEPTIONS'][$idx]['due']) {
            if (is_array($task['recurrence']['EXCEPTIONS'][$idx]['due'])) {
              $task['recurrence']['EXCEPTIONS'][$idx]['due'] = new DateTime($task['recurrence']['EXCEPTIONS'][$idx]['due']['date'], new DateTimeZone($task['recurrence']['EXCEPTIONS'][$idx]['due']['timezone'] ? $task['recurrence']['EXCEPTIONS'][$idx]['due']['timezone'] : $this->plugin->timezone));
            }
            $task['recurrence']['EXCEPTIONS'][$idx]['due'] = $task['recurrence']['EXCEPTIONS'][$idx]['due']->setTimezone($tz);
          }
        }
      }
      if (is_array($task['recurrence']) && is_array($task['recurrence']['EXDATE'])) {
        foreach ($task['recurrence']['EXDATE'] as $idx => $exdate) {
          if (is_array($task['recurrence']['EXDATE'][$idx])) {
            $task['recurrence']['EXDATE'][$idx] = new DateTime($task['recurrence']['EXDATE'][$idx]['date'], new DateTimeZone($task['recurrence']['EXDATE'][$idx]['timezone'] ? $task['recurrence']['EXDATE'][$idx]['timezone'] : $this->plugin->timezone));
          }
          $task['recurrence']['EXDATE'][$idx] = $task['recurrence']['EXDATE'][$idx]->setTimezone($tz);
        }
      }
      return $task;
    }
}