<?php

/**
 * CalDAV driver for the Calendar plugin
 *
 * @author Daniel Morlock <daniel.morlock@awesome-it.de>
 * @author Roland 'rosali' Liebl <dev-team@myroundcube.com>
 *
 * Copyright (C) 2013, Awesome IT GbR <info@awesome-it.de>
 * Copyright (C) 2014, MyRoundcube.com <dev-team@myroundcube.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once (INSTALL_PATH . 'plugins/libgpl/calendar/drivers/database/database_driver.php');
require_once (INSTALL_PATH . 'plugins/libgpl/tasklist/drivers/tasklist_driver.php');
require_once (INSTALL_PATH . 'plugins/libgpl/tasklist/drivers/database/tasklist_database_driver.php');
require_once (INSTALL_PATH . 'plugins/libgpl/tasklist/drivers/caldav/tasklist_caldav_driver.php');
require_once (INSTALL_PATH . 'plugins/libgpl/caldav/caldav_sync.php');
require_once (INSTALL_PATH . 'plugins/libgpl/encryption/encryption.php');

/**
 * TODO
 * - Database constraint: obj_id, obj_type must be unique.
 * - Postgresql, Sqlite scripts.
 *
 */

class caldav_driver extends database_driver
{
    //const DB_DATE_FORMAT = 'Y-m-d H:i:s';
    //const DB_DATE_FORMAT_ALLDAY = 'Y-m-d 00:00:00';
    const OBJ_TYPE_VCAL   = 'vcal';
    const OBJ_TYPE_VEVENT = 'vevent';
    const OBJ_TYPE_VTODO  = 'vtodo';
    
    const FREEBUSY_UNKNOWN   = 0;
    const FREEBUSY_FREE      = 1;
    const FREEBUSY_BUSY      = 2;
    const FREEBUSY_TENTATIVE = 3;
    const FREEBUSY_OOF       = 4;
    
    protected $db_calendars = 'calendars';
    protected $db_calendars_caldav_props = 'calendars_caldav_props';
    protected $db_events = 'vevent';
    protected $db_events_caldav_props = 'vevent_caldav_props';
    protected $db_tasks = 'tasks';
    protected $db_tasks_caldav_props = 'vtodo_caldav_props';
    protected $db_attachments = 'vevent_attachments';
    
    protected $cache_slots_args;
    protected $cache_slots = array();

    protected $cal;
    protected $tasks;
    protected $rc;
    protected $updates;
    protected $current_cal_id;

    protected $crypt_key;

    static protected $debug = null;

    // features this backend supports
    public $alarms = true;
    public $attendees = true;
    public $freebusy = false;
    public $attachments = true;
    public $alarm_types = array('DISPLAY', 'EMAIL');
    public $last_error;

    protected $sync_clients = array();


    /**
     * Default constructor
     */
    public function __construct($cal)
    {
        $this->cal = $cal;
        $this->rc = $cal->rc;
        
        if (!$this->cal->timezone) {
          try {
            $this->cal->timezone = new DateTimeZone($this->rc->config->get('timezone', 'UTC'));
          }
          catch (Exception $e) {
            $this->cal->timezone = new DateTimeZone('UTC');
          }
        }
        
        $this->crypt_key = $this->rc->config->get("calendar_crypt_key", "%E`c{2;<J2F^4_&._BxfQ<5Pf3qv!m{e");
        
        $this->freebusy = true;

        parent::__construct($cal);

        // Set debug state
        if(self::$debug === null)
            self::$debug = $this->rc->config->get('calendar_caldav_debug', false);

        $this->_init_sync_clients();
    }

    /**
     * Helper method to log debug msg if debug mode is enabled.
     */
    static public function debug_log($msg)
    {
        if(self::$debug === true)
            rcmail::console(__CLASS__.': '.$msg);
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
    protected function _set_caldav_props($obj_id, $obj_type, array $props, $caller = false)
    {
        $now = date(self::DB_DATE_FORMAT);
        if ($obj_type == 'vcal')
        {
            $db_table = $this->_get_table($this->db_calendars_caldav_props);
            $fields = " (obj_id, obj_type, url, tag, " . $this->rc->db->quote_identifier('user') . ", pass, sync, authtype, last_change) ";
            $values = "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }
        else
        {
            $db_table = $this->_get_table($this->db_events_caldav_props);
            $fields = " (obj_id, obj_type, url, tag, " . $this->rc->db->quote_identifier('user') . ", pass, last_change) ";
            $values = "VALUES (?, ?, ?, ?, ?, ?, ?)";
        }
        
        $event = $this->_get_id(array('id' => $obj_id));
        $obj_id = $event['id'];

        $this->_remove_caldav_props($obj_id, $obj_type);

        $password = isset($props["pass"]) ? $props["pass"] : null;
        if ($password) {
            $e = new Encryption(MCRYPT_BlOWFISH, MCRYPT_MODE_CBC);
            $p = $e->encrypt($password, $this->crypt_key);
            $password = base64_encode($p);
        }

        $query = $this->rc->db->query(
            "INSERT INTO " . $db_table . $fields .
            $values,
            $obj_id,
            $obj_type,
            $props["url"],
            isset($props["tag"]) ? $props["tag"] : null,
            isset($props["user"]) ? $props["user"] : null,
            $password,
            $props['sync'] ? $props['sync'] : 5,
            $props['authtype'] ? $props['authtype'] : 'detect',
            $now
        );
        
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
    protected function _get_caldav_props($obj_id, $obj_type)
    {
        if ($obj_type == 'vcal')
        {
            $db_table = $this->_get_table($this->db_calendars_caldav_props);
        }
        else if ($obj_type == 'vevent')
        {
            $db_table = $this->_get_table($this->db_events_caldav_props);
        }
        if ($obj_type == 'vtodo')
        {
            $db_table = $this->_get_table($this->db_tasks_caldav_props);
        }
        
        $event = $this->_get_id(array('id' => $obj_id));

        $result = $this->rc->db->query(
            "SELECT * FROM " . $db_table . " p ".
            "WHERE p.obj_type = ? AND p.obj_id = ? ", $obj_type, $event['id']);

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
    protected function _remove_caldav_props($obj_id, $obj_type)
    {
        if ($obj_type == 'vcal')
        {
            $db_table = $this->_get_table($this->db_calendars_caldav_props);
        }
        else
        {
            $db_table = $this->_get_table($this->db_events_caldav_props);
        }
        
        $query = $this->rc->db->query(
            "DELETE FROM " . $db_table . " ".
            "WHERE obj_type = ? AND obj_id = ? ", $obj_type, $obj_id);

        return $this->rc->db->affected_rows($query);
    }

    /**
     * Determines whether the given calendar is in sync regarding
     * calendar's ctag and the configured sync period.
     *
     * @param int Calender id.
     * @return boolean True if calendar is in sync, true otherwise.
     */
    protected function _is_synced($cal_id)
    {
        $now = date(self::DB_DATE_FORMAT);
        $last = date(self::DB_DATE_FORMAT, time() - $this->sync_clients[$cal_id]->sync * 60);

        // Atomic sql: Check for exceeded sync period and update last_change.
        $query = $this->rc->db->query(
            "UPDATE " . $this->_get_table($this->db_calendars_caldav_props) ." " .
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
    protected function _expand_pass(& $props)
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
     * Get a list of available calendars from this source
     *
     * @param bool $active Return only active calendars
     * @param bool $personal Return only personal calendars
     *
     * @return array List of calendars
     */
    public function list_calendars($active = false, $personal = false)
    {
        // Read calendars from database and remove those without iCAL props.
        $calendars = array();
        foreach(parent::list_calendars($active, $personal) as $id => $cal)
        {
            if($this->_get_caldav_props($id, self::OBJ_TYPE_VCAL) !== false)
                $calendars[$id] = $cal;
        }
        return $calendars;
    }

    /**
     * Initializes calendar sync clients.
     *
     * @param array $cal_ids Optional list of calendar ids. If empty, caldav_driver::list_calendars()
     *              will be used to retrieve a list of calendars.
     */
    protected function _init_sync_clients($cal_ids = array())
    {
        if(sizeof($cal_ids) == 0) $cal_ids = array_keys($this->list_calendars());
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
                $this->sync_clients[$cal_id] = new caldav_sync($cal_id, $props, $sslverify, 'caldav_driver');
            }
        }
    }

    /**
     * Auto discover principal available to the user on the DAV server
     * @param array $props
     *    url: Absolute URL to DAV server
     *    user: Username
     *    pass: Password
     * @return array
     *    href: Absolute calendar URL
     */
    public function autodiscover_principal($props)
    {
        $current_user_principal = array('{DAV:}current-user-principal');

        require_once (INSTALL_PATH . 'plugins/libgpl/caldav/caldav-client.php');

        $caldav = new caldav_client($props['url'], $props['user'], $props['pass'], $props['authtype'], array($this->rc->config->get('calendar_curl_verify_peer', true), $this->rc->config->get('calendar_curl_verify_host', true)));

        $tokens = parse_url($props['url']);
        $base_uri = $tokens['scheme'].'://'.$tokens['host'].($tokens['port'] ? ':'.$tokens['port'] : null);
        $caldav_url = $props['url'];
        $response = $caldav->prop_find($caldav_url, $current_user_principal, 0);
        if($principal = $response['{DAV:}current-user-principal'])
        {
            return $base_uri . $principal;
        }
        else
        {
            return false;
        }
    }
    
    /**
     * Auto discover calendar home available to the user on the DAV server
     * @param array $props
     *    url: Absolute URL to DAV server
     *    user: Username
     *    pass: Password
     * @return array
     *    href: Absolute calendar URL
     */
    public function autodiscover_calendars_home($props)
    {
        $current_user_principal = array('{DAV:}current-user-principal');
        $calendar_home_set = array('{urn:ietf:params:xml:ns:caldav}calendar-home-set');
        $cal_attribs = array('{DAV:}resourcetype', '{DAV:}displayname');

        require_once (INSTALL_PATH . 'plugins/libgpl/caldav/caldav-client.php');

        $caldav = new caldav_client($props['url'], $props['user'], $props['pass'], $props['authtype'], array($this->rc->config->get('calendar_curl_verify_peer', true), $this->rc->config->get('calendar_curl_verify_host', true)));

        $tokens = parse_url($props['url']);
        $base_uri = $tokens['scheme'].'://'.$tokens['host'].($tokens['port'] ? ':'.$tokens['port'] : null);
        $caldav_url = $props['url'];
        $response = $caldav->prop_find($caldav_url, $current_user_principal, 0);
        if (!$response) {
            self::debug_log("Resource \"$caldav_url\" has no collections.");
            return false;
        }
        $caldav_url = $base_uri . $response[$current_user_principal[0]];
        $response = $caldav->prop_find($caldav_url, $calendar_home_set, 0);
        if (!$response) {
            self::debug_log("Resource \"$caldav_url\" contains no calendars.");
            return false;
        }
        if (strtolower(substr($response[$calendar_home_set[0]], 0, 4)) == 'http') {
            return $response[$calendar_home_set[0]];
        }
        else {
            return $base_uri . $response[$calendar_home_set[0]];
        }
    }
    
    /**
     * Auto discover calenders available to the user on the caldav server
     * @param array $props
     *    url: Absolute URL to calendar server
     *    user: Username
     *    pass: Password
     * @return array
     *    name: Calendar display name
     *    href: Absolute calendar URL
     */
    public function autodiscover_calendars($props)
    {
        $calendars = array();
        $current_user_principal = array('{DAV:}current-user-principal');
        $calendar_home_set = array('{urn:ietf:params:xml:ns:caldav}calendar-home-set');
        $cal_attribs = array('{DAV:}resourcetype', '{DAV:}displayname');

        require_once (INSTALL_PATH . 'plugins/libgpl/caldav/caldav-client.php');

        $caldav = new caldav_client($props['url'], $props['user'], $props['pass'], $props['authtype'], array($this->rc->config->get('calendar_curl_verify_peer', true), $this->rc->config->get('calendar_curl_verify_host', true)));

        $tokens = parse_url($props['url']);
        $base_uri = $tokens['scheme'].'://'.$tokens['host'].($tokens['port'] ? ':'.$tokens['port'] : null);
        $caldav_url = $props['url'];
        $response = $caldav->prop_find($caldav_url, $current_user_principal, 0);
        if (!$response) {
            self::debug_log("Resource \"$caldav_url\" has no collections.");
            return $calendars;
        }
        $caldav_url = $base_uri . $response[$current_user_principal[0]];
        $response = $caldav->prop_find($caldav_url, $calendar_home_set, 0);
        if (!$response) {
            self::debug_log("Resource \"$caldav_url\" contains no calendars.");
            return $calendars;
        }
        if (strtolower(substr($response[$calendar_home_set[0]], 0, 4)) == 'http') {
            $caldav_url = $response[$calendar_home_set[0]];
        }
        else {
            $caldav_url = $base_uri . $response[$calendar_home_set[0]];
        }
        $response = $caldav->prop_find($caldav_url, $cal_attribs, 1);
        $categories = $this->rc->config->get('calendar_categories', array());
        foreach($response as $collection => $attribs)
        {
            $found = false;
            $name = '';
            foreach($attribs as $key => $value)
            {
                if ($key == '{DAV:}resourcetype' && is_object($value)) {
                    if ($value instanceof Sabre\DAV\Property\ResourceType) {
                        $values = $value->getValue();
                        if (in_array('{urn:ietf:params:xml:ns:caldav}calendar', $values)) {
                            $found = true;
                        }
                    }
                }
                else if ($key == '{DAV:}displayname') {
                    $name = $value;
                }
            }
            if ($found) {
                array_push($calendars, array(
                    'name'  => $name ? $name : ucwords(end(explode('/', unslashify($base_uri . $collection)))),
                    'href'  => $base_uri . $collection,
                    'color' => $categories[$name] ? $categories[$name] : $props['color'],
                ));
            }
        }
        return $calendars;
    }

    /**
     * Encodes directory- and filenames using rawurlencode().
     *
     * @see http://stackoverflow.com/questions/7973790/urlencode-only-the-directory-and-file-names-of-a-url
     * @param string Unencoded URL to be encoded.
     * @return Encoded URL.
     */
    protected static function _encode_url($url)
    {
        // Don't encode if "%" is already used.
        if(strstr($url, '%') === false)
        {
            return preg_replace_callback('#://([^/]+)/([^?]+)#', function ($matches) {
                return '://' . $matches[1] . '/' . join('/', array_map('rawurlencode', explode('/', $matches[2])));
            }, $url);
        }
        else return $url;
    }

    /**
     * Add default (pre-installation provisioned) calendar. If calendars from 
     * same url exist, insertion does not take place. Same for previously
     * deleted calendars
     *
     * @param array $props
     *    caldav_url: Absolute URL to calendar server collection
     *    caldav_user: Username
     *    caldav_pass: Password
     *    name: Calendar name
     *    color: Events color
     *    showalarms: Display alarms
     *    tasks: Handle tasks
     *    freebusy: Allow freebusy requests
     * @return bool false on creation error, true otherwise
     *    
     */
    public function insert_default_calendar($props) {
        if ($props['driver'] == 'caldav') {
            $found = false;
            foreach ($this->list_calendars() as $cal) {
                $vcal_info = $this->_get_caldav_props($cal['id'], self::OBJ_TYPE_VCAL);
                if (stripos(self::_encode_url($vcal_info['url']), self::_encode_url($props['caldav_url'])) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return $this->create_calendar($props);
            }
        }
        return true;
    }

    /**
     * Callback function to produce driver-specific calendar create/edit form
     *
     * @param string Request action 'form-edit|form-new'
     * @param array  Calendar properties (e.g. id, color)
     * @param array  Edit form fields
     *
     * @return string HTML content of the form
     */
    public function calendar_form($action, $calendar, $formfields)
    {
        $cal_id = $calendar['id'];
        $props = $this->_get_caldav_props($cal_id, self::OBJ_TYPE_VCAL);

        $protected = array();
        $preinstalled_calendars = $this->rc->config->get('calendar_preinstalled_calendars', array());
        foreach($preinstalled_calendars as $idx => $properties)
        {
            if($properties['driver'] == 'caldav')
            {
                $url = str_replace('@', urlencode('@'), str_replace('%u', $this->rc->get_user_name(), $properties['caldav_url']));
                if(stripos($props['url'], $url) === 0)
                {
                    $protected = $properties['protected'];
                    break;
                }
            }
        }
        
        if($protected['name'])
        {
            $formfields['name'] = str_replace('<input ', '<input readonly="readonly" ', $formfields['name']);
        }
        
        if($protected['color'])
        {
            unset($formfields['color']);
        }
        
        if($protected['showalarms'])
        {
            $formfields['showalarms'] = str_replace('<input ', '<input disabled="disabled" ', $formfields['showalarms']);
        }

        if($this->freebusy)
        {
            $enabled = ($action == 'form-new') ? true : $this->calendars[$cal_id]['freebusy'];
            $readonly = array();
            if($protected['freebusy'])
            {
                $readonly = array('disabled' => 'disabled');
            }
            $input_freebusy = new html_checkbox(array_merge(array(
                "name" => "freebusy",
                "title" => $this->cal->gettext("allowfreebusy"),
                "id" => "chbox_freebusy",
                "value" => 1,
            ), $readonly));
        
            $formfields['freebusy'] = array(
              "label" => $this->cal->gettext('freebusy'),
              "value" => $input_freebusy->show($enabled?1:0),
              "id" => "freebusy",
            );
        }
        
        $enabled = ($action == 'form-new') ? true : $this->calendars[$cal_id]['evts'];
        $readonly = array();
        if($protected['events'])
        {
            $readonly = array('disabled' => 'disabled');
        }
        $input_events = new html_checkbox(array_merge(array(
            "name" => "events",
            "id" => "chbox_events",
            "value" => 1,
        ), $readonly));
        
        $formfields["events"] = array(
            "label" => $this->cal->gettext('events'),
            "value" => $input_events->show($enabled?1:0),
            "id" => "events",
        );

        if(!$this->rc->config->get('calendar_disable_tasks', false))
        {
            $enabled = ($action == 'form-new') ? true : $this->calendars[$cal_id]['tasks'];
            $readonly = array();
            if($protected['tasks'])
            {
                $readonly = array('disabled' => 'disabled');
            }
            $input_tasks = new html_checkbox(array_merge(array(
                "name" => "tasks",
                "id" => "chbox_tasks",
                "value" => 1,
            ), $readonly));
        
            $formfields["tasks"] = array(
                "label" => $this->cal->gettext("tasks"),
                "value" => $input_tasks->show($enabled?1:0),
                "id" => "tasks",
            );
        }
        
        if(!isset($formfields['caldav_url']))
        {
            $readonly = array();
            if(stripos($props['url'], 'https://apidata.googleusercontent.com/caldav/v2/') === 0 || $protected['caldav_url'])
            {
                $readonly = array('readonly' => 'readonly');
            }
            $input_caldav_url = new html_inputfield(array_merge(array(
                "name" => "caldav_url",
                "id" => "caldav_url_input",
                "size" => 45,
                "placeholder" => "http://dav.mydomain.tld/calendars/john.doh@mydomain.tld",
            ), $readonly));

            $formfields["caldav_url"] = array(
                "label" => $this->cal->gettext("url"),
                "value" => $input_caldav_url->show($props["url"]),
                "id" => "caldav_url",
            );
        }
        
        if(!isset($formfields['caldav_user']))
        {
            $readonly = array();
            if(stripos($props['url'], 'https://apidata.googleusercontent.com/caldav/v2/') === 0 || $protected['caldav_user'])
            {
                $readonly = array('readonly' => 'readonly');
            }
            $input_caldav_user = new html_inputfield(array_merge(array(
                "name" => "caldav_user",
                "id" => "caldav_user_input",
                "size" => 30,
                "placeholder" => "john.doh@mydomain.tld",
            ), $readonly));

            $formfields["caldav_user"] = array(
                "label" => $this->cal->gettext("username"),
                "value" => $input_caldav_user->show($props["user"]),
                "id" => "caldav_user",
            );
        }
        
        if(!isset($formfields['caldav_pass']))
        {
            $readonly = array();
            if(stripos($props['url'], 'https://apidata.googleusercontent.com/caldav/v2/') === 0 || $protected['caldav_pass'])
            {
                $readonly = array('readonly' => 'readonly');
            }
            $input_caldav_pass = new html_passwordfield(array_merge(array(
                "name" => "caldav_pass",
                "id" => "caldav_pass_input",
                "size" => 30,
                "placeholder" => "******",
            ), $readonly));

            $formfields["caldav_pass"] = array(
                "label" => $this->cal->gettext("password"),
                "value" => $input_caldav_pass->show(null), // Don't send plain text password to GUI
                "id" => "caldav_pass",
            );
        }
        if(!isset($formfields['authtype']))
        {
            $readonly = array();
            if(stripos($props['url'], 'https://apidata.googleusercontent.com/caldav/v2/') === 0 || $protected['authtype'])
            {
                $readonly = array('disabled' => 'disabled');
            }
            $field_id = 'authtype_select';
            $select = new html_select(array_merge(array('name' => 'authtype', 'id' => $field_id), $readonly));
            $types = array('detect' => 'detect', 'basic' => 'basic', 'digest' => 'digest');
            foreach($types as $type => $text){
                $select->add($this->cal->gettext('libgpl.' . $text), $type);
            }
            $formfields['authtype'] = array(
                'label' => $this->cal->gettext('libgpl.authtype'),
                'value' => $select->show($props['authtype'] ? $props['authtype'] : 'detect'),
                'id' => 'sync_interval',
            );
        }
        if(!isset($formfields['sync_interval']))
        {
            $readonly = array();
            if($protected['sync'])
            {
                $readonly = array('disabled' => 'disabled');
            }
            $field_id = 'sync_interval_select';
            $select = new html_select(array_merge(array('name' => 'sync', 'id' => $field_id), $readonly));
            $intervals = array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 10 => 10, 15 => 15, 30 => 30, 45 => 45, 60 => 60);
            foreach($intervals as $interval => $text){
                $select->add($text, $interval);
            }
            $formfields['sync_interval'] = array(
                'label' => $this->cal->gettext('sync_interval'),
                'value' => $select->show((int) ($props['sync'] ? $props['sync'] : 5)) . '&nbsp;' . $this->cal->gettext('minute_s'),
                'id' => 'sync_interval',
            );
        }
        
        foreach($formfields as $key => $val)
        {
            if(empty($val))
            {
                unset($formfields[$key]);
            }
        }
        
        return parent::calendar_form($action, $calendar, $formfields);
    }

    /**
     * Extracts caldav properties and creates calendar.
     *
     * @see database_driver::create_calendar()
     */
    public function create_calendar($prop)
    {
        $result = false;
        $props = $prop;
        $props['url'] = self::_encode_url($prop['caldav_url']);
        
        if(!isset($props['color'])) {
            $props['color'] = 'cc0000';
        }
        
        $props['user'] = $prop['caldav_user'];
        $props['pass'] = $prop['caldav_pass'];
        $pwd_expanded_props = $props;
        $this->_expand_pass($pwd_expanded_props);

        if($redirect = $this->_check_redirection($pwd_expanded_props)) {
          $pwd_expanded_props['url'] = $props['url'] = $prop['url'] = $redirect;
        }
        
        if(!$props['url'])
        {
            return false;
        }
        
        if(!$this->_check_connection($pwd_expanded_props) && $pwd_expanded_props['pass'] != '***TOKEN***')
        {
            return false;
        }

        if($prop['autodiscover']){
            $calendars = $this->autodiscover_calendars($pwd_expanded_props);
        }
        else
        {
            $calendars = array(
                0 => array(
                    'name'  => $props['name'],
                    'href'  => $props['url'],
                    'color' => $props['color'],
                ),
            );
        }

        foreach($calendars as $idx => $calendar)
        {
            $removed = $this->rc->config->get('calendar_caldavs_removed', array());
            $props['url'] = self::_encode_url($props['url']);
            if(isset($removed[slashify($props['url'])]))
            {
                unset($calendars[$idx]);
                if(!empty($calendars) || $this->rc->action == '') // Take the user input
                {
                    continue;
                }
                else
                {
                    $calendars[0] = $pwd_expanded_props;
                    $calendars[0]['href'] = $props['url'];
                }
            }

            $result = $this->rc->db->query(
                "SELECT * FROM " . $this->_get_table($this->db_calendars_caldav_props) .
                " WHERE url LIKE ?",
                $calendar['href']
            );
            $result = $this->rc->db->fetch_assoc($result);
            if(is_array($result))
            {
                $result = $this->rc->db->query(
                    "SELECT calendar_id FROM " . $this->_get_table($this->db_calendars) .
                    " WHERE user_id = ? and calendar_id = ?",
                    $this->rc->user->ID,
                    $result['obj_id']
                );
                $result = $this->rc->db->fetch_assoc($result);
                if(is_array($result))
                {
                    unset($calendars[$idx]);
                }
            }
        }

        $cal_ids = array();
        if(sizeof($calendars) > 0)
        {
            $result = true;
            foreach ($calendars as $calendar)
            {
                $props['url'] = self::_encode_url($calendar['href']);
                $props['name'] = $calendar['name'];
                $props['color'] = $calendar['color'];
                $props['sync'] = $calendar['sync'] ? $calendar['sync'] : $props['sync'];
                $props['authtype'] = $calendar['authtype'] ? $calendar['authtype'] : $props['authtype'];

                if (($obj_id = parent::create_calendar($props)) !== false) {
                    $result = $result && $this->_set_caldav_props($obj_id, self::OBJ_TYPE_VCAL, $props, 'create_calendar');
                    array_push($cal_ids, $obj_id);
                }
            }
        }
        else
        {
            // Fallback: Assume given URL as resource to a calendar.
            if (!isset($removed[slashify($props['url'])]))
            {
                if (($obj_id = parent::create_calendar($props)) !== false) {
                    $result = $this->_set_caldav_props($obj_id, self::OBJ_TYPE_VCAL, $props, 'create_calendar');
                    array_push($cal_ids, $obj_id);
                }
            }
            else
            {
                return true;
            }
        }

        // Re-read calendars to internal buffer.
        $this->_read_calendars();

        // Initial sync of newly created calendars.
        if (!empty($cal_ids))
        {
            $this->_init_sync_clients($cal_ids);
            foreach($cal_ids as $cal_id){
                $this->_sync_calendar($cal_id, true);
            }
        }
        
        return $result ? $obj_id : $result;
    }

    /**
     * Extracts caldav properties and updates calendar.
     *
     * @see database_driver::edit_calendar()
     */
    public function edit_calendar($prop)
    {
        $protected = array();
        $preinstalled_calendars = $this->rc->config->get('calendar_preinstalled_calendars', array());
        foreach($preinstalled_calendars as $idx => $properties)
        {
            if($properties['driver'] == 'caldav')
            {
                $url = str_replace('@', urlencode('@'), str_replace('%u', $this->rc->get_user_name(), $properties['caldav_url']));
                if(stripos($prop['caldav_url'], $url) === 0)
                {
                    $protected = $properties['protected'];
                    $protected['unsubscribe'] = isset($properties['unsubscribe']) ? $properties['unsubscribe'] : 1; 
                    foreach($protected as $key => $val)
                    {
                        if(($val && $key != 'caldav_user' && $key != 'caldav_pass' && $key != 'caldav_url' && $key != 'name') || $key == 'unsubscribe')
                        {
                            $prop[$key] = $properties[$key];
                        }
                    }
                    break;
                }
            }
        }
        
        $prev_prop = $this->_get_caldav_props($prop['id'], self::OBJ_TYPE_VCAL);
        $props['user']     = $prop['caldav_user'];
        $props['pass']     = $prop['caldav_pass'] ? $prop['caldav_pass'] : $prev_prop['pass'];
        $props['url']      = $prop['caldav_url'];
        $props['authtype'] = $prop['authtype'];
        $pwd_expanded_props = $props;
        $this->_expand_pass($pwd_expanded_props);

        if($redirect = $this->_check_redirection($pwd_expanded_props))
        {
            $pwd_expanded_props['url'] = $props['url'] = $prop['url'] = $redirect;
        }

        if($pwd_expanded_props['pass'] != '***TOKEN***' && !$props['url'])
        {
            return false;
        }

        if(stripos($props['url'], 'https://apidata.googleusercontent.com/caldav/v2/') === false && !$this->_check_connection($pwd_expanded_props))
        {
            return false;
        }

        if (parent::edit_calendar($prop, $prop['events']) !== false)
        {

            // Don't change the password if not specified
            if(!$prop['caldav_pass']) {
                if($prev_prop) $prop['caldav_pass'] = $prev_prop['pass'];
            }
            
            return $this->_set_caldav_props($prop['id'], self::OBJ_TYPE_VCAL, array(
                'url'      => self::_encode_url($prop['caldav_url']),
                'user'     => $prop['caldav_user'],
                'pass'     => $prop['caldav_pass'],
                'sync'     => $prop['sync'],
                'authtype' => $prop['authtype'],
                'edit_calendar'
            ));
        }

        return false;
    }

    /**
     * Deletes caldav properties and the appropriate calendar.
     *
     * @see database_driver::remove_calendar()
     */
    public function remove_calendar($prop)
    {
        $removed = $this->_get_caldav_props($prop['id'], self::OBJ_TYPE_VCAL);
        
        $protected = array();
        $preinstalled_calendars = $this->rc->config->get('calendar_preinstalled_calendars', array());
        foreach($preinstalled_calendars as $idx => $properties)
        {
            if($properties['driver'] == 'caldav')
            {
                $url = str_replace('@', urlencode('@'), str_replace('%u', $this->rc->get_user_name(), $properties['caldav_url']));
                if(stripos($removed['url'], $url) === 0)
                {
                    if(isset($properties['deleteable']) && !$properties['deleteable'])
                    {
                        $this->last_error = $this->rc->gettext('calendar.protected');
                        return false;
                    }
                }
            }
        }
        
        if(is_array($removed))
        {
            $removed = slashify($removed['url']);
            $removed = array_merge($this->rc->config->get('calendar_caldavs_removed', array()), array($removed => time()));
            $this->rc->user->save_prefs(array('calendar_caldavs_removed' => $removed));
        }
         
        if (parent::remove_calendar($prop, 'caldav'))
        {
            self::debug_log("Removed calendar \"".$prop['id']."\".");
            return true;
        }

        return false;
    }
    
    /**
     * Check of an URL
     *
     * @param array Indexed array user, pass, url
     * @return boolean true or false
     */
    protected function _check_redirection($prop)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $prop['url']);
        curl_setopt($ch, CURLOPT_USERPWD, $prop['user'] . ':' . $prop['pass']);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        //curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_exec($ch);
        if(!curl_errno($ch))
        {
            $info = curl_getinfo($ch);
            $code = $info['http_code'];
            if((substr($code, 0, 1) == 2 || substr($code, 0, 1) == 2) && isset($info['url']))
            {
                $success = $info['url'];
            }
            else
            {
                $success = false;
                $this->last_error = $this->rc->gettext('calendar.connectionfailed');
            }
        }
        else
        {
            $success = false;
            $this->last_error = $this->rc->gettext('calendar.connectionfailed');
        }
        curl_close($ch);
        return $success;
    }
    
    /**
     * Check connection to a CalDAV ressource
     *
     * @param array Indexed array user, pass, url
     * @param boolean second attempt (true, false)
     * @return boolean success (true, false)
     */
    protected function _check_connection($prop, $retry = false)
    {
        require_once (INSTALL_PATH . 'plugins/libgpl/caldav/caldav-client.php');
        $prop['url'] = self::_encode_url($prop['url']);
        $caldav = new caldav_client($prop['url'], $prop['user'], $prop['pass'], $prop['authtype'], array($this->rc->config->get('calendar_curl_verify_peer', true), $this->rc->config->get('calendar_curl_verify_peer', true)));
        $caldav_url = $prop['url'];
        $current_user_principal = array('{DAV:}current-user-principal');
        $response = $caldav->prop_find($caldav_url, $current_user_principal, 0, false);
        if(!$response)
        {
            $this->last_error = $this->rc->gettext('calendar.connectionfailed');
            if(!$retry)
            {
                $this->_add_collection($prop);
                return $this->_check_connection($prop, true);
            }
            else
            {
                return false;
            }
        }
        else
        {
            return true;
        }
    }
    
    /**
     * Add a caldendar collection
     *
     * @param array Indexed array user, pass, url, displayname
     * @return boolean success (true, false)
     */
    protected function _add_collection($prop)
    {
        require_once (INSTALL_PATH . 'plugins/libgpl/caldav/caldav-client.php');
        $prop['url'] = self::_encode_url($prop['url']);
        $caldav = new caldav_client($prop['url'], $prop['user'], $prop['pass'], $prop['authtype'], array($this->rc->config->get('calendar_curl_verify_peer', true), $this->rc->config->get('calendar_curl_verify_peer', true)));
        return $caldav->add_collection($prop['url'], $prop['name'], 'calendar', 'caldav');
    }

    /**
     * Performs caldav updates on given events.
     *
     * @param array Caldav and event properties to update. See caldav_sync::get_updates().
     * @return array List of event ids.
     */
    public function perform_updates($updates, $callback = true, $force = false)
    {
        $event_ids = array();

        $num_created = 0;
        $num_updated = 0;
        
        foreach($updates as $update)
        {
            if($update['remote_event']['_type'] == 'event')
            {
                // local event -> update event
                if(isset($update["local_event"]))
                {
                    $local_event = (array)$update["local_event"];
                    unset($local_event["attachments"]);

                    if(parent::edit_event($update["remote_event"] + $local_event))
                    {
                        $event_id = $update["local_event"]["id"];
                        self::debug_log("Updated event \"$event_id\".");

                        $props = array(
                            "url" => $update["url"],
                            "tag" => $update["etag"]
                        );

                        $this->_set_caldav_props($event_id, self::OBJ_TYPE_VEVENT, $props, 'perform_updates - edit');
                        array_push($event_ids, $event_id);
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
                    $event_id = parent::new_event($update["remote_event"]);

                    // check for attachments (otherwise they will be lost)
                    $result = $this->rc->db->limitquery(
                        "SELECT event_id FROM " . $this->_get_table($this->db_events) . "
                        WHERE uid = ? AND calendar_id = ?",
                        0,
                        1,
                        $update["remote_event"]["uid"],
                        $update["remote_event"]["calendar"]
                    );
                
                    if($result && $event = $this->rc->db->fetch_assoc($result))
                    {
                        $attachments = array();
                        $result = $this->rc->db->query(
                            "SELECT * FROM " . $this->_get_table($this->db_attachments) . "
                            WHERE event_id = ?",
                            $event['event_id']
                        );
                        while($result && $attachment = $this->rc->db->fetch_assoc($result))
                        {
                            array_push($attachments, $attachment);
                        }
                    }
                
                    if($event_id)
                    {
                        self::debug_log("Created event \"$event_id\".");

                        $props = array(
                            "url" => $update["url"],
                            "tag" => $update["etag"]
                        );

                        $this->_set_caldav_props($event_id, self::OBJ_TYPE_VEVENT, $props, 'perform_updates - new');
                        array_push($event_ids, $event_id);
                        $num_created ++;
                    
                        if(is_array($attachments))
                        {
                            foreach($attachments as $attachment)
                            {
                                $this->rc->db->query(
                                    "INSERT INTO " . $this->_get_table($this->db_attachments) . "
                                    (event_id, filename, mimetype, size, data) VALUES (?, ?, ?, ?, ?)",
                                    $event_id,
                                    $attachment['filename'],
                                    $attachment['mimetype'],
                                    $attachment['size'],
                                    $attachment['data']
                                );
                            }
                        }
                    }
                    else
                    {
                        self::debug_log("Could not perform event creation: " . print_r($update, true));
                    }
                }
            }
        }

        self::debug_log("Created $num_created new events, updated $num_updated event.");

        if($callback && ($this->rc->action == 'refresh' || $force))
        {
            $this->tasks = new tasklist_caldav_driver($this->cal, false);

            foreach($updates as $idx => $update)
            {
                if($local_event = $this->tasks->get_task($update['remote_event']['uid']))
                {
                    $local_event['task_id'] = $local_event['id'];
                    $updates[$idx]['local_event'] = $local_event;
                }
            }

            $updated_task_ids = $this->tasks->perform_updates($updates, false);
            
            if(is_array($this->updates))
            {
                list($this->updates, $synced_task_ids) = $this->updates;

                $tasks = array();
                foreach($this->tasks->load_all_tasks($this->current_cal_id) as $task)
                {
                    if($task["recurrence_id"] == 0)
                    {
                        array_push($tasks, $task);
                    }
                }
                foreach($tasks as $task)
                {
                    
                    if(array_search($task['task_id'] , $updated_task_ids) === false && // No updated task
                        array_search($task['task_id'] . '-' . $task['uid'], $synced_task_ids) === false) // No in-sync task
                    {
                        // Assume: Task not in sync and not updated, so delete!
                        $task['id'] = $task['task_id'];
                        $this->tasks->delete_task($task, true);
                        self::debug_log("Remove task \"" . $task['id'] . "\".");
                    }
                }
            }
        }
        
        return $event_ids;
    }

    /**
     * Return all events from the given calendar.
     *
     * @param int Calendar id.
     * @return array
     */
    public function load_all_events($cal_id)
    {
        $end = defined(PHP_INT_MAX) ? PHP_INT_MAX : strtotime('2029-12-31 00:00:00');
        return parent::load_events(0, $end, null, array($cal_id), 0);
    }
    
    /**
     * Return all tasks from the given calendar.
     *
     * @param int Calendar id.
     * @return array
     */
    public function load_all_tasks($cal_id)
    {
        return parent::load_tasks(array($cal_id));
    }

    /**
     * Synchronizes events of given calendar.
     *
     * @param int Calendar id.
     * @param boolean force tasks synchronization
     */
    protected function _sync_calendar($cal_id, $force = false)
    {
        self::debug_log("Syncing calendar id \"$cal_id\".");
        
        $this->current_cal_id = $cal_id;
        if(! $cal_sync = $this->sync_clients[$cal_id])
        {
            self::debug_log("No sync client for calendar id \"$cal_id\".");
            return;
        }
        
        $events = array();
        $caldav_props = array();

        foreach($this->load_all_events($cal_id) as $event)
        {
            if($event['recurrence_id'] == 0)
            {
                $id = $event['id'];
                $event['id'] .= '-' . $event['uid'];
                array_push($events, $event);
                array_push($caldav_props,
                    $this->_get_caldav_props($id, self::OBJ_TYPE_VEVENT));
            }
        }
        
        foreach($this->load_all_tasks($cal_id) as $event)
        {
            if($event['recurrence_id'] == 0)
            {
                $id = $event['id'];
                $event['id'] .= '-' . $event['uid'];
                array_push($events, $event);
                array_push($caldav_props,
                    $this->_get_caldav_props($id, self::OBJ_TYPE_VTODO));
            }
        }

        $updates = $cal_sync->get_updates($events, $caldav_props);

        if($updates)
        {
            $this->updates = $updates;
            list($updates, $synced_event_ids) = $updates;
            $updated_event_ids = $this->perform_updates($updates, true, $force);

            // Delete events that are not in sync or updated.
            foreach($events as $event)
            {
                if(array_search($event["id"], $updated_event_ids) === false && // No updated event
                    array_search($event["id"], $synced_event_ids) === false) // No in-sync event
                {
                    if(!$event['exdate'])
                    {
                        // Assume: Event not in sync and not updated, so delete!
                        parent::remove_event($event, true);
                        self::debug_log("Remove event \"".$event["id"]."\".");
                    }
                }
            }
           
            // Update calendar ctag ...
            $cal_props = $this->_get_caldav_props($cal_id, self::OBJ_TYPE_VCAL);
            $cal_props["tag"] = $cal_sync->get_ctag();
            $this->_set_caldav_props($cal_id, self::OBJ_TYPE_VCAL, $cal_props, '_sync_calendar');
        }

        self::debug_log("Successfully synced calendar id \"$cal_id\".");
    }

    /**
     * Get real database identifier
     */
    protected function _get_id($event)
    {
        if($id = $event['id'])
        {
            $event['id'] = current(explode('-', $id));
        }
    
      return $event;
    }
  
    /**
     * Synchronizes events and loads them.
     *
     * @see database_driver::load_events()
     */
    public function load_events($start, $end, $query = null, $cal_ids = null, $virtual = 1, $modifiedsince = null, $force = false)
    {
        foreach($this->sync_clients as $cal_id => $cal_sync) {
            if($this->calendars[$cal_id]['active'])
            {
                if($force || !$this->_is_synced($cal_id))
                {
                    $this->_sync_calendar($cal_id, $force);
                }
            }
        }
        if($force)
        {
            return true;
        }
        else
        {
            return parent::load_events($start, $end, $query, $cal_ids, $virtual, $modifiedsince);
        }
    }

    /**
     * Add a single event to the database and to the caldav server.
     *
     * @param array Hash array with event properties
     * @return int Event id on success, false otherwise.
     * @see database_driver::new_event()
     */
    public function new_event($event)
    {
        if($event['_type'] == 'task')
        {
            $this->tasks = new tasklist_caldav_driver($this->cal, false);
            $event['list'] = $event['calendar'];
            unset($event['calendar']);
            return $this->tasks->create_task($event);
        }
        else
        {
            $event_id = parent::new_event($event);
            $cal_id = $event['calendar'];
            if($event_id !== false)
            {
                $sync_client = $this->sync_clients[$cal_id];
                $event = $this->_save_preprocess($event);
                $props = $sync_client->create_event($event);
                if($props === false)
                {
                    self::debug_log("Unkown error while creating caldav event, undo creating local event \"$event_id\"!");
                    parent::remove_event($event);
                    return false;
                }
                else
                {
                    self::debug_log("Successfully pushed event \"$event_id\" to caldav server.");
                    $this->_set_caldav_props($event_id, self::OBJ_TYPE_VEVENT, $props, 'new_event');

                    // Trigger calendar sync to update ctags and etags.
                    if($this->rc->action != 'import_events')
                    {
                        $this->_sync_calendar($cal_id);
                    }

                    return $event_id;
                }
            }
        }
        
        return false;
    }

    /**
     * Update the event entry with the given data and sync with caldav server.
     *
     * @param array Hash array with event properties
     * @param array Internal use only, filled with non-modified event if this is second try after a calendar sync was enforced first.
     * @see calendar_driver::edit_event()
     */
    public function edit_event($event, $old_event = null)
    {
        if($event['_savemode'] == 'new')
        {
            $event['uid'] = $this->cal->generate_uid();
            return $this->new_event($event);
        }
        else
        {
            $event = $this->_get_id($event);
        
            $sync_enforced = ($old_event != null);
            $event_id = (int)$event['id'];
            $cal_id = $event['calendar'];

            if($old_event == null)
            {
                $old_event = parent::get_master($event);
                $old_event = $this->_save_preprocess($old_event);
            }

            if(parent::edit_event($event))
            {
                // Get updates event and push to caldav.
                $event = parent::get_master(array('id' => $event_id));
                $event = $this->_save_preprocess($event);
                $sync_client = $this->sync_clients[$cal_id];
                $props_id = $event['current']['recurrence_id'] ? (int)$event['current']['recurrence_id'] : $event_id;
                $props = $this->_get_caldav_props($props_id, self::OBJ_TYPE_VEVENT);
                if(is_array($props))
                {
                    $success = $sync_client->update_event($event, $props);
                }
                else
                {
                    $success = false;
                }
                if($success === true)
                {
                    self::debug_log("Successfully updated event \"$event_id\".");

                    // Trigger calendar sync to update ctags and etags.
                    $this->_sync_calendar($cal_id);

                    return true;
                }
                else if(!$success && !$sync_enforced)
                {
                    self::debug_log("Event \"$event_id\", tag \"".$props["tag"]."\" not up to date, will update calendar first ...");
                    $this->_sync_calendar($cal_id, true);

                    return $this->edit_event($event, $old_event); // Re-try after re-sync
                }
                else
                {
                    self::debug_log("Unkown error while updating caldav event, undo updating local event \"$event_id\"!");
                    parent::edit_event($old_event);

                    return false;
                }
            }
        }

        return false;
    }
    
    /**
     * Move a single event
     *
     * @param array Hash array with event properties
     * @see calendar_driver::move_event()
     */
    public function move_event($event)
    {
        $event = $this->_get_id($event);
        
        // let edit_event() do all the magic
        return $this->edit_event($event + (array)parent::get_master($event));
    }

    /**
     * Resize a single event
     *
     * @param array Hash array with event properties
     * @see calendar_driver::resize_event()
     */
    public function resize_event($event)
    {
        $event = $this->_get_id($event);
        
        // let edit_event() do all the magic
        return $this->edit_event($event + (array)parent::get_master($event));
    }

    /**
     * Remove a single event from the database and from caldav server.
     *
     * @param array Hash array with event properties
     * @param boolean Remove record irreversible
     *
     * @see calendar_driver::remove_event()
     */
    public function remove_event($obj, $force = true)
    {
        $obj = $this->_get_id($obj);
        
        $event_id = (int)$obj['id'];
        $cal_id = (int)$obj['calendar'];
        $savemode = $obj['_savemode'] ? $obj['_savemode'] : 'all';
        $event = parent::get_master($obj);
        if($event['recurrence_id'])
        {
           $event_id = (int)$event['recurrence_id'];
           $event['id'] = $event_id;
           unset($event['recurrence_id']);
        }
        if(!$props = $this->_get_caldav_props($event_id, self::OBJ_TYPE_VEVENT))
        {
          $savemode = 'current';
          $event = parent::get_event($event['uid']);
          $props = $this->_get_caldav_props($event['id'], self::OBJ_TYPE_VEVENT);
        }

        if(parent::remove_event($obj, $force) && is_array($props))
        {
            $sync_client = $this->sync_clients[$cal_id];
            
            switch($savemode)
            {
                case 'current':
                case 'future':
                    $event = parent::get_master($event);
                    $event = $this->_save_preprocess($event);
                    $success = $sync_client->update_event($event, $props);
                    break;
                default: // all is default
                    $success = $sync_client->remove_event($props);
                    break;
            }
            
            if($success === true)
            {
                self::debug_log("Successfully removed event \"$event_id\".");
            }
            else
            {
                self::debug_log("Unkown error while removing caldav event \"$event_id\", force sync calendar \"$cal_id\"!");
            }
            // Trigger calendar sync to update ctags and etags.
            $this->_sync_calendar($cal_id);

            return $success;
        }

        return false; // Unkown error.
    }
    
    /**
     * Get a list of pending alarms to be displayed to the user
     *
     * @see calendar_driver::pending_alarms()
     */
    public function pending_alarms($time, $calendars = null)
    {
        // handled by database driver (don't return duplicates)
        return array();
    }
    
    /**
     * Feedback after showing/sending an alarm notification
     *
     * @see calendar_driver::dismiss_alarm()
     */
    public function dismiss_alarm($event_id, $snooze = 0)
    {
        $success = parent::dismiss_alarm($event_id, $snooze);
        
        $success = false;
        
        $result = $this->rc->db->limitquery(
            "SELECT calendar_id FROM " . $this->_get_table($this->db_events) . "
            WHERE event_id = ?",
            0,
            1,
            $event_id
        );
        
        $result = $this->rc->db->fetch_assoc($result);
        
        if(is_array($result))
        {
            $cal_id = $result['calendar_id'];
            $props = $this->_get_caldav_props($event_id, self::OBJ_TYPE_VEVENT);
            
            $sync_client = $this->sync_clients[$cal_id];
            
            if(is_array($props))
            {
                if($event = parent::get_master(array('id' => $event_id), false))
                {
                    $event = $this->_save_preprocess($event);
                    $success = $sync_client->update_event($event, $props);
                }
                else
                {
                    $success = false;
                }
            }
            else
            {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Fetch free/busy information from a person within the given range
     *
     * @param string  username of attendee
     * @param integer Requested period start date/time as unix timestamp
     * @param integer Requested period end date/time as unix timestamp
     *
     * @return array  List of busy timeslots within the requested range
     */
    public function get_freebusy_list($user, $start, $end)
    {
        if($this->cache_slots_args == serialize(func_get_args()))
        {
            return $this->cache_slots;
        }

        $return_freebusy = false;
        $ical = $this->cal->lib->get_ical();
        $sql = 'SELECT * FROM ' . $this->_get_table($this->db_calendars_caldav_props) . ' WHERE ' . $this->rc->db->quote_identifier('user') . ' = ? AND obj_type = ?';
        $result = $this->rc->db->query($sql, $user, 'vcal');
        $slots = array();
        while($result && $props = $this->rc->db->fetch_assoc($result))
        {
            $props = $this->_get_caldav_props($props['obj_id'], $props['obj_type']);
            $sql = 'SELECT * FROM ' . $this->_get_table($this->db_calendars) . ' WHERE calendar_id = ?';
            $calendar = array();
            if($result2 = $this->rc->db->limitquery($sql, 0, 1, $props['obj_id']))
            {
                $calendar = $this->rc->db->fetch_assoc($result2);
            }
            if(!$calendar['freebusy'])
            {
                continue;
            }
            if($props['pass'] == '%p' && $props['user'] != $this->rc->user->data['username'])
            {
                continue;
            }
            else
            {
                $return_freebusy = true;
                $this->_expand_pass($props);
            }
            
            $parsed = calendar::caldav_freebusy($props, $start, $end);
            if(is_array($parsed))
            {
                foreach($parsed as $periods)
                {
                    foreach($periods['periods'] as $slot)
                    {
                        switch($slot[2])
                        {
                            case 'FREE':
                                continue;
                                break;
                            case 'BUSY':
                                $status = self::FREEBUSY_BUSY;
                                break;
                            case 'BUSY-TENTATIVE':
                                $status = self::FREEBUSY_TENTATIVE;
                                break;
                            case 'OOF':
                                $status = self::FREEBUSY_OOF;
                                break;
                            default:
                                $status = self::FREEBUSY_UNKNOWN;
                                break;
                        }
                        $slots[] = array(
                            $slot[0]->format('U'),
                            $slot[1]->format('U'),
                            $status
                        );
                    }
                }
            }
        }
        
        if($return_freebusy && empty($slots))
        {
            $slots[] = array(
                $start,
                $end,
                self::FREEBUSY_FREE,
            );
        }
        
        $this->cache_slots_args = serialize(func_get_args());
        $this->cache_slots = $slots;

        return $this->cache_slots;
    }
    
    /**
     * Final event modifications before passing to CalDAV client
     *
     * @param array  event
     *
     * @return array modified event
     */
    protected function _save_preprocess($event)
    {
        if(!$event['end'])
        {
            $event['end'] = $event['start'];
        }
        if($event['allday'])
        {
            $event['start']->_dateonly = true;
            $event['end']->_dateonly = true;
            if($event['recurrence_date'])
            {
                $event['recurrence_date']->_dateonly = true;
            }
            if(is_array($event['recurrence']) && is_array($event['recurrence']['RDATE']))
            {
                foreach($event['recurrence']['RDATE'] as $idx => $rdate)
                {
                    $event['recurrence']['RDATE'][$idx]->_dateonly = true;
                }
            }
            if(is_array($event['recurrence']) && is_array($event['recurrence']['EXCEPTIONS']))
            {
                  foreach($event['recurrence']['EXCEPTIONS'] as $idx => $exception)
                  {
                      $event['recurrence']['EXCEPTIONS'][$idx]['start']->_dateonly = true;
                      $event['recurrence']['EXCEPTIONS'][$idx]['start']->_dateonly = true;
                  }
            }
            if(is_array($event['recurrence']) && is_array($event['recurrence']['EXDATE']))
            {
                  foreach($event['recurrence']['EXDATE'] as $idx => $exdate)
                  {
                      $event['recurrence']['EXDATE'][$idx]->_dateonly = true;
                  }
            }
        }
        else // icloud does not like TZIDs
        {
            $event['props'] = $this->_get_caldav_props($event['calendar'], 'vcal');
            if(preg_match('#(http|https)://[p0-9]*\-caldav\.icloud\.com/[0-9]*/calendars/(home|tasks)#i', $event['props']['url']))
            {
                $tz = new DateTimezone('UTC');
                $event['start']->setTimezone($tz);
                $event['end']->setTimezone($tz);
                if($event['recurrence_date'])
                {
                    $event['recurrence_date'] = $event['recurrence_date']->setTimezone($tz);
                }
                if(is_array($event['recurrence']) && is_array($event['recurrence']['RDATE']))
                {
                    foreach($event['recurrence']['RDATE'] as $idx => $rdate)
                    {
                        $event['recurrence']['RDATE'][$idx] = $event['recurrence']['RDATE'][$idx]->setTimezone($tz);
                    }
                }
                if(is_array($event['recurrence']) && is_array($event['recurrence']['EXCEPTIONS']))
                {
                    foreach($event['recurrence']['EXCEPTIONS'] as $idx => $exception)
                    {
                        $event['recurrence']['EXCEPTIONS'][$idx]['start'] = $event['recurrence']['EXCEPTIONS'][$idx]['start']->setTimezone($tz);
                        $event['recurrence']['EXCEPTIONS'][$idx]['end'] = $event['recurrence']['EXCEPTIONS'][$idx]['end']->setTimezone($tz);
                    }
                }
                if(is_array($event['recurrence']) && is_array($event['recurrence']['EXDATE']))
                {
                    foreach($event['recurrence']['EXDATE'] as $idx => $exdate)
                    {
                        $event['recurrence']['EXDATE'][$idx] = $event['recurrence']['EXDATE'][$idx]->setTimezone($tz);
                    }
                }
            }
        }
        return $event;
    }
    
    /**
     * Get database table name
     *
     * @param string  default database table name
     *
     * @return string  database table named as configured (custom name / prefix)
     */
    protected function _get_table($table)
    {
        return $this->rc->config->get('db_table_' . $table, $this->rc->db->table_name($table));
    }
}
?>