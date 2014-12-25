<?php

/**
 * Database driver for the Tasklist plugin
 *
 * @version @package_version@
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @author Roland 'Rosali' Liebl <dev-team@myroundcube.com>
 *
 * Copyright (C) 2012, Kolab Systems AG <contact@kolabsys.com>
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

class tasklist_database_driver extends tasklist_driver
{
  const IS_COMPLETE_SQL = "(status='COMPLETED' OR (complete=1 AND status=''))";
  const DB_DATE_FORMAT = 'Y-m-d H:i:s';
    
  public $undelete = false; // yes, we can
  public $sortable = false;
  public $alarms = false;
  public $alarm_types = array('DISPLAY', 'EMAIL');
  public $attachments = true;

  private $rc;
  private $plugin;
  protected $lists = array(); // Mod by Rosali (declare protected)
  protected $list_ids = '';    // Mod by Rosali (declare protected)

  private $db_tasks = 'vtodo';
  private $db_lists = 'calendars'; // Mod by Rosali
  private $db_attachments = 'vtodo_attachments';
  private $task_uid;

  /**
   * Default constructor
   */
  public function __construct($plugin)
  {
    $this->rc = $plugin->rc;
    $this->plugin = $plugin;

    // read database config
    $db = $this->rc->get_dbh();
    $this->db_lists = $this->rc->config->get('db_table_lists', $db->table_name($this->db_lists));
    $this->db_tasks = $this->rc->config->get('db_table_tasks', $db->table_name($this->db_tasks));

    $this->_read_lists();
  }

  /**
   * Read available calendars for the current user and store them internally
   */
  private function _read_lists()
  {
    if (!empty($this->rc->user->ID)) {
      $list_ids = array();
      $result = $this->rc->db->query(
        "SELECT *, calendar_id AS id FROM " . $this->db_lists . "
         WHERE user_id=?
         ORDER BY CASE WHEN name='INBOX' THEN 0 ELSE 1 END, name",
         $this->rc->user->ID
      );
      while ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
        if ($arr['tasks']) {
          $arr['showalarms'] = intval($arr['showalarms']);
          $arr['active'] = ($arr['tasks'] < 2) ? true : false;
          $arr['name'] = html::quote($arr['name']);
          $arr['listname'] = html::quote($arr['name']);
          $arr['editable'] = $arr['readonly'] ? false : true;
          $this->lists[$arr['id']] = $arr;
          $list_ids[] = $this->rc->db->quote($arr['id']);
        }
      }
      $this->list_ids = join(',', $list_ids);
    }
  }

  /**
   * Get a list of available tasks lists from this source
   */
  public function get_lists($active = false)
  {
    // attempt to create a default list for this user
    if (empty($this->lists)) {
      if ($this->create_list(array('name' => 'Default', 'color' => '000000')))
        $this->_read_lists();
    }
    if ($active) {
      foreach ($this->lists as $idx => $list) {
        if (!$list['active']) {
          unset($this->lists[$idx]);
        }
      }
    }
    return $this->lists;
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
    $result = $this->rc->db->query(
      "INSERT INTO " . $this->db_lists . "
      (user_id, name, color, showalarms)
      VALUES (?, ?, ?, ?)",
      $this->rc->user->ID,
      strval($prop['name']),
      strval($prop['color']),
      $prop['showalarms']?1:0
    );

    if ($result)
      return $this->rc->db->insert_id($this->db_lists);

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
      SET name = ?, color = ?, showalarms = ?
      WHERE tasklist_id = ?
      AND ser_id = ?",
      $prop['name'],
      $prop['color'],
      $prop['showalarms']?1:0,
      $prop['id'],
      $this->rc->user->ID
    );

    return $this->rc->db->affected_rows($query);
  }

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
        $list_id = $prop['id'];

        if ($this->lists[$list_id]) {
            // delete all tasks linked with this list
            $this->rc->db->query(
                "DELETE FROM " . $this->db_tasks . "
                 WHERE tasklist_id=?",
                $list_id
            );

            // delete list record
            $query = $this->rc->db->query(
                "DELETE FROM " . $this->db_lists . "
                 WHERE tasklist_id=?
                 AND user_id=?",
                $list_id,
                $this->rc->user->ID
            );

            return $this->rc->db->affected_rows($query);
        }

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
        if (empty($lists))
            $lists = array_keys($this->lists);
        else if (is_string($lists))
            $lists = explode(',', $lists);

        // only allow to select from lists of this user
        $list_ids = array_map(array($this->rc->db, 'quote'), array_intersect($lists, array_keys($this->lists)));

        $result = $this->rc->db->query(sprintf(
            "SELECT * FROM " . $this->db_tasks . "
             WHERE tasklist_id IN (%s)
             AND del=0 AND exdate IS NULL AND NOT " . self::IS_COMPLETE_SQL,
            join(',', $list_ids)
        ));

        $counts = array('all' => 0, 'flagged' => 0, 'today' => 0, 'tomorrow' => 0, 'overdue' => 0, 'nodate' => 0);
        while ($result && ($rec = $this->rc->db->fetch_assoc($result))) {
            if (!$this->_is_exception($rec)) {
                $counts = $this->_counts($rec, $counts);
            }
            if ($rec['startdate'] && $rec['recurrence']) {
                $clones = (array) $this->_get_recurrences($rec, false);
                foreach ($clones as $clone) {
                    $counts = $this->_counts($clone, $counts);
                }
            }
        }

        return $counts;
    }
    
    /*
     * Count
     */
    private function _counts($rec, $counts)
    {
        $today_date = new DateTime('now', $this->plugin->timezone);
        $today = $today_date->format('Y-m-d');
        $tomorrow_date = new DateTime('now + 1 day', $this->plugin->timezone);
        $tomorrow = $tomorrow_date->format('Y-m-d');
        $counts['all']++;
        if ($rec['flagged'])
            $counts['flagged']++;
        if (empty($rec['date']))
            $counts['nodate']++;
        if ($rec['startdate'] <= $today) {
            $counts['today']++;
        }
        else if ($rec['date'] <= $today) {
            $counts['today']++;
        }
        if (empty($rec['date']))
            $counts['nodate']++;
        if ($rec['startdate'] <= $tomorrow)
            $counts['tomorrow']++;
        else if ($rec['date'] <= $tomorrow)
            $counts['tomorrow']++;
        if (!empty($rec['date']) && $rec['date'] < $today)
            $counts['overdue']++;

        return $counts;
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
        if (empty($lists))
            $lists = array_keys($this->lists);
        else if (is_string($lists))
            $lists = explode(',', $lists);
        // only allow to select from lists of this user
        $list_ids = array_map(array($this->rc->db, 'quote'), array_intersect($lists, array_keys($this->lists)));
        $sql_add = '';

        // add filter criteria
        if ($filter['from'] || ($filter['mask'] & tasklist::FILTER_MASK_TODAY)) {
            $sql_add .= ' AND (date IS NULL OR date >= ?)';
            $datefrom = $filter['from'];
        }
        if ($filter['to']) {
            if ($filter['mask'] & tasklist::FILTER_MASK_OVERDUE)
                $sql_add .= ' AND (date IS NOT NULL AND date <= ' . $this->rc->db->quote($filter['to']) . ')';
            else
                $sql_add .= ' AND (date IS NULL OR date <= ' . $this->rc->db->quote($filter['to']) . ')';
        }

        // special case 'today': also show all events with date before today
        if ($filter['mask'] & tasklist::FILTER_MASK_TODAY) {
            $datefrom = date('Y-m-d', 0);
        }

        if ($filter['mask'] & tasklist::FILTER_MASK_NODATE)
            $sql_add = ' AND date IS NULL';

        if ($filter['mask'] & tasklist::FILTER_MASK_COMPLETE)
            $sql_add .= ' AND ' . self::IS_COMPLETE_SQL;
        else if (empty($filter['since']))  // don't show complete tasks by default
            $sql_add .= ' AND NOT ' . self::IS_COMPLETE_SQL;

        if ($filter['mask'] & tasklist::FILTER_MASK_FLAGGED)
            $sql_add .= ' AND flagged>0'; // Mod by Rosali

        // compose (slow) SQL query for searching
        // FIXME: improve searching using a dedicated col and normalized values
        if ($filter['search']) {
            $sql_query = array();
            foreach (array('title','description','organizer','attendees') as $col)
                $sql_query[] = $this->rc->db->ilike($col, '%'.$filter['search'].'%');
            $sql_add = 'AND (' . join(' OR ', $sql_query) . ')';
        }

        if ($filter['since'] && is_numeric($filter['since'])) {
            $sql_add .= ' AND changed >= ' . $this->rc->db->quote(date('Y-m-d H:i:s', $filter['since']));
        }

        $tasks = array();

        if (!empty($list_ids)) {
            $result = $this->rc->db->query(sprintf(
                "SELECT * FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND del=0 AND exdate is null
                 %s
                 ORDER BY parent_id, task_id, uid, exception, exdate ASC",
                 join(',', $list_ids),
                 $sql_add
                ),
                $datefrom
           );
           while ($result && ($rec = $this->rc->db->fetch_assoc($result))) {
                $has_children = $this->get_childs(array('id' => $rec['task_id']), true);
                if (!empty($has_children)) {
                  $rec['has_children'] = true;
                }
                if ($rec['startdate'] && $rec['recurrence'] && $virtual) {
                    $has_parent = false;
                    if (!$this->_is_exception($rec)) {
                      $has_parent = true;
                      $tasks[] = $this->_read_postprocess($rec);
                    }
                    $clones = (array) $this->_get_recurrences($rec, $has_parent);
                    $tasks = array_merge($tasks, $clones);
                }
                else {
                    if ($rec['parent_id']) {
                      $parent = $this->get_task(array('id' => $rec['parent_id']));
                      if ($this->_is_exception($parent)) {
                        unset($rec['parent_id']);
                      }
                    }
                    $tasks[] = $this->_read_postprocess($rec);
                }
           }
        }

        return $tasks;
    }

    /**
     * Return data of a specific task
     *
     * @param mixed  Hash array with task properties or task UID
     * @return array Hash array with task properties or false if not found
     */
    public function get_task($prop)
    {
        if (is_string($prop)) {
            $query_col = 'uid';
            $search = $prop;
        } else if(is_array($prop) && !isset($prop['id'])) {
            $query_col = 'uid';
            $search = $prop['uid'];
        } else {
            $query_col = 'task_id';
            $search = $prop['id'];
        }
        $result = $this->rc->db->query(sprintf(
             "SELECT * FROM " . $this->db_tasks . "
              WHERE tasklist_id IN (%s)
              AND %s=?
              AND del=0",
              $this->list_ids,
              $query_col
             ),
             $search
        );

        if ($result && ($rec = $this->rc->db->fetch_assoc($result))) {
             return $this->_read_postprocess($rec);
        }

        return false;
    }
    
    /**
     * Return data of current task
     * @param array Hash array with task properties
     */
    public function get_master($task)
    {
      $id = is_array($task) ? ($task['id'] ? $task['id'] : $task['uid']) : $task;
      $col = is_array($task) && is_numeric($id) ? 'task_id' : 'uid';

      if ($active) {
        $list = $this->lists;
        foreach ($lists as $idx => $list) {
          if (!$list['active']) {
            unset($lists[$idx]);
          }
        }
        $lists = join(',', $lists);
      }
      else {
        $lists = $this->list_ids;
      }

      $result = $this->rc->db->query(sprintf(
        "SELECT e.*, (SELECT COUNT(attachment_id) FROM " . $this->db_attachments . " 
          WHERE task_id = e.task_id OR task_id = e.recurrence_id) AS _attachments
        FROM " . $this->db_tasks . " AS e
        WHERE e.tasklist_id IN (%s)
        AND e.$col=?",
        $lists
        ),
      $id);
      if ($result && ($task = $this->rc->db->fetch_assoc($result)) && $task['task_id']) {
        $task = $this->_read_postprocess($task);
        if ($task['recurrence_id'] || $task['recurrence']) {
          $exceptions = $this->_get_master($task['recurrence_id'] ? $task['recurrence_id'] : $task['id'], $lists);
          if (is_array($exceptions)) {
            $curr_task = $task;
            $task = $exceptions['parent'];
            if (is_array($exceptions['exceptions'])) {
              $task['recurrence']['EXCEPTIONS'] = $exceptions['exceptions'];
            }
            if (is_array($exceptions['exdates'])) {
              $task['recurrence']['EXDATE'] = $exceptions['exdates'];
            }
            $task['current'] = $curr_task;
          }
        }
        unset($task['exception'], $task['exdate']);

        return $task;
      }

      return false;
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
        // resolve UID first
        if (is_string($prop)) {
            $result = $this->rc->db->query(sprintf(
                "SELECT task_id AS id, tasklist_id AS list FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND uid = ?",
                 $this->list_ids
                ),
                $prop);
            $prop = $this->rc->db->fetch_assoc($result);
        }

        $childs = array();
        $task_ids = array($prop['id']);

        // query for childs (recursively)
        while (!empty($task_ids)) {
            $ids = '(';
            foreach($task_ids as $id) {
              $ids .= 'parent_id LIKE ' . $this->rc->db->quote($id  . '%') . ' OR ';
            }
            $ids = substr($ids, 0, strlen($ids) - 4);
            $ids .= ')';
            $result = $this->rc->db->query(sprintf(
                "SELECT task_id AS id FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND %s
                 AND del = 0",
                $this->list_ids,
                $ids
            ));
            $task_ids = array();
            while ($result && ($rec = $this->rc->db->fetch_assoc($result))) {
                $childs[] = $rec['id'];
                $task_ids[] = $rec['id'];
            }

            if (!$recursive)
                break;
        }

        return $childs;
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
        if (empty($lists))
            $lists = array_keys($this->lists);
        else if (is_string($lists))
            $lists = explode(',', $lists);

        // only allow to select from tasklists with activated alarms
        $list_ids = array();
        foreach ($lists as $lid) {
            if ($this->lists[$lid] && $this->lists[$lid]['showalarms'])
                $list_ids[] = $lid;
        }
        $list_ids = array_map(array($this->rc->db, 'quote'), $list_ids);

        $alarms = array();
        if (!empty($list_ids)) {
            $result = $this->rc->db->query(sprintf(
                "SELECT * FROM " . $this->db_tasks . "
                 WHERE tasklist_id IN (%s)
                 AND notify <= %s AND NOT " . self::IS_COMPLETE_SQL,
                join(',', $list_ids),
                $this->rc->db->fromunixtime($time)
            ));

            while ($result && ($rec = $this->rc->db->fetch_assoc($result)))
                if (stripos($rec['alarms'], ':DISPLAY') !== false) // Mod by Rosali
                    $alarms[] = $this->_read_postprocess($rec);
        }

        return $alarms;
    }

    /**
     * Feedback after showing/sending an alarm notification
     *
     * @see tasklist_driver::dismiss_alarm()
     */
    public function dismiss_alarm($task_id, $snooze = 0)
    {
        $notify_at = null; //default 
    
        $task = $this->get_master(array('id' => $task_id));
    
        if ($snooze > 0) {
            $notify_at = date(self::DB_DATE_FORMAT, time() + $snooze);
        }
        else if ($task['recurrence'] && $task['id'] == $task_id) {
            $start = $task['startdate'] . ($task['starttime'] ? (' ' . $task['starttime']) : '');
            $task['start'] = new DateTime($start, $this->plugin->timezone);
            $engine = libcalendaring::get_recurrence();
            $rrule = $this->unserialize_recurrence($task['recurrence']);
            $engine->init($rrule, $task['start']->format('Y-m-d H:i:s'));
            while ($next_start = $engine->next()) {
                $next_start->setTimezone($this->plugin->timezone);
                if ($next_start > new DateTime(date(self::DB_DATE_FORMAT, strtotime('+1 day')))) {
                    $task['start'] = $next_start;
                    $alarm = libcalendaring::get_next_alarm($task);
                    if ($alarm['time']) {
                        $notify_at = date(self::DB_DATE_FORMAT, $alarm['time']);
                    }
                    break;
                }
            }
        }
    
        $query = $this->rc->db->query(sprintf(
            "UPDATE " . $this->db_tasks . "
            SET   changed=%s, notify=?
            WHERE task_id=?
            AND tasklist_id IN (" . $this->list_ids . ")",
            $this->rc->db->now()),
            $notify_at,
            $task_id
        );
    
        return $this->rc->db->affected_rows($query);
    }
    
    /**
     * Remove alarm dismissal or snooze state
     *
     * @param  string  Task identifier
     */
    public function clear_alarms($id)
    {
        // Nothing to do here. Alarms are reset in edit_task()
    }

    /**
     * Return execptions and exdate data of a recurring task
     * @param int task database identifier
     * @param string tasklist ids (separated by comma)
     * @return array indexed array (parent = parent task, exceptions = all exception (RECURRENCE-ID), exdates = EXDATES)
     */
    private function _get_master($task_id, $lists)
    {
      $result = $this->rc->db->query(sprintf(
        "SELECT * FROM " . $this->db_tasks . "
          WHERE (task_id = ? OR recurrence_id = ?) AND tasklist_id IN (%s)",
          $lists
        ),
        $task_id,
        $task_id
      );
      
      $tasks = array();
      while ($result && $task = $this->rc->db->fetch_assoc($result)) {
        if (!$task['recurrence_id']) {
          $tasks['parent'] = $this->_read_postprocess($task);
        }
        else if ($task['exception']) {
          $tasks['exceptions'][$task['exception']] = $this->_read_postprocess($task);
        }
        else if ($task['exdate']) {
          $tasks['exdates'][$task['exdate']] = new DateTime($task['exdate']);
        }
      }
      return $tasks;
    }


    /**
     * Map some internal database values to match the generic "API"
     */
    private function _read_postprocess($rec)
    {
        $rec['id'] = $rec['task_id'];
        $rec['list'] = $rec['tasklist_id'];
        $rec['changed'] = new DateTime($rec['changed']);
        $rec['tags'] = array_filter(explode(',', $rec['tags']));

        if (!$rec['parent_id'])
            unset($rec['parent_id']);

        // decode serialze recurrence rules
        if ($rec['recurrence']) {
            $rec['recurrence'] = $this->unserialize_recurrence($rec['recurrence']);
        }
    
        if ($rec['exception']) {
            $rec['recurrence_date'] = new DateTime($rec['exception'], $this->plugin->timezone);
        }
        
        unset($rec['task_id'], $rec['tasklist_id'], $rec['created']);
        return $rec;
    }

    /**
     * Add a single task to the database
     *
     * @param array Hash array with task properties (see header of this file)
     * @return mixed New event ID on success, False on error
     * @see tasklist_driver::create_task()
     */
    public function create_task($prop)
    {
        // check list permissions
        $list_id = $prop['list'] ? $prop['list'] : reset(array_keys($this->lists));

        if (!$this->lists[$list_id] || $this->lists[$list_id]['readonly'])
            return false;

        foreach (array('parent_id', 'date', 'time', 'startdate', 'starttime', 'alarms', 'recurrence', 'status') as $col) {
            if (empty($prop[$col]))
                $prop[$col] = null;
        }

        // Begin mod by Rosali (use DateTime objects if present)
        if (isset($prop['changed']) && is_a($prop['changed'], 'DateTime')) {
            $ts_changed = date(self::DB_DATE_FORMAT, $prop['changed']->format('U'));
        }
        else {
            $ts_changed = date(self::DB_DATE_FORMAT);
        }
        if (isset($prop['created']) && is_a($prop['created'], 'DateTime')) {
            $ts_created = date(self::DB_DATE_FORMAT, $prop['created']->format('U'));
        }
        else {
            $ts_created = date(self::DB_DATE_FORMAT);
        }
        
        $prop['recurrence'] = is_array($prop['recurrence']) ? $this->serialize_recurrence($prop['recurrence']) : null;
        
        if($this->task_uid != $prop['uid']) { // Mod by Rosali
          $this->task_uid = $prop['uid'];
          $notify_at = $this->_get_notification($prop);
          $result = $this->rc->db->query(sprintf(
            "INSERT INTO " . $this->db_tasks . "
            (tasklist_id, uid, parent_id, created, changed, title, date, time, startdate, starttime, complete, status, flagged, description, tags, alarms, recurrence, notify)
            VALUES (?, ?, ?, %s, %s, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
              $this->rc->db->quote($ts_created),
              $this->rc->db->quote($ts_changed) // End mod by Rosali
            ),
            $list_id,
            $prop['uid'],
            $prop['parent_id'],
            $prop['title'],
            $prop['date'],
            $prop['time'],
            $prop['startdate'],
            $prop['starttime'],
            $prop['complete'] ? $prop['complete'] : 0,
            $prop['status'] ? $prop['status'] : 0,
            $prop['flagged'] ? $prop['flagged'] : 0,
            strval($prop['description']),
            join(',', (array)$prop['tags']),
            $prop['alarms'],
            $prop['recurrence'],
            $notify_at
          );
          if ($result) {
            $insert_id = $this->rc->db->insert_id($this->db_tasks);
            $prop['id'] = $insert_id;
            $this->_update_recurrences($prop);
          
            return $insert_id;
          }
        }
        else {
          $this->_update_recurrences($prop);
          $result = $this->rc->db->limitquery(
            "SELECT task_id FROM " . $this->db_tasks . "
             WHERE uid=?",
            0,
            1,
            $prop['uid']
          );
          $result = $this->rc->db->fetch_assoc($result);
          return is_array($result) ? $result['task_id'] : false;
        }

        return false;
    }

  /**
   * Update an task entry with the given data
   *
   * @param array Hash array with task properties
   * @return boolean True on success, False on error
   * @see tasklist_driver::edit_task()
   */
  public function edit_task($prop)
  {
    $prop = $this->_get_id($prop);
    $priority = $prop['flagged'] ? intval($prop['flagged']) : ($prop['priority'] ? intval($prop['priority']) : 0);
    $prop['flagged'] = $priority;
    $old = $this->get_master($prop);
    $prop = $this->_increase_sequence($prop, $old);

    if (is_array($prop['recurrence'])) {
      $recurrence = $prop['recurrence'];
      $prop['recurrence'] = $this->serialize_recurrence($prop['recurrence']);
    }
    // modify a recurring event, check submitted savemode to do the right things
    $savemode = $prop['_savemode'];
    if ($savemode) {
      switch ($savemode) {
        case 'new':
          $prop['uid'] = $this->plugin->generate_uid();
          return $this->create_task($prop);

        case 'current':
          $start = $prop['startdate'] . ($prop['starttime'] ? (' ' . $prop['starttime'] . ':00') : '');
          $prop['recurrence_date'] = $prop['start'] = new DateTime($start, $this->plugin->timezone);
          $prop['uid'] = $old['uid'];
          $prop['recurrence_id'] = $old['id'];
          $old = $this->get_master($prop);
          unset($prop['recurrence']);
          $old['recurrence']['EXCEPTIONS'][$prop['recurrence_date']->format('Y-m-d H:i:s')] = $prop;
          $prop = $old;
          return $this->_update_recurrences($prop, true);

        case 'future':
          $start = $prop['startdate'] . ($prop['starttime'] ? (' ' . $prop['starttime'] . ':00') : '');
          $until = new DateTime($start, $this->plugin->timezone);
          $until = $until->modify('-1 day');
          $prop['recurrence'] = $old['recurrence'];
          $prop['parent_id'] = null;
          $old['recurrence']['UNTIL'] = $until;
          $old['recurrence'] = $this->serialize_recurrence($old['recurrence']);
          if ($success = $this->_update_task($old, false)) {
            $prop['uid'] = $this->plugin->generate_uid();
            if ($success = $this->create_task($prop)) {
              $this->_clear_recurrences($prop['id'], $start);
            }
            return $success;
          }
          break;

          case 'all':
            $prop['start'] = new DateTime($prop['startdate'] . ($prop['starttime'] ? (' ' . $prop['starttime'] . ':00') : '00:00:00'), $this->plugin->timezone);
            $unix_start = $prop['start']->format('U');
            $old['start'] = new DateTime($old['startdate'] . ($old['starttime'] ? (' ' . $old['starttime'] . ':00') : '00:00:00'), $this->plugin->timezone);
            $unix_start_old = $old['start']->format('U');
            $diff = $unix_start - $unix_start_old;
            $tz_start = $prop['start']->getTimezone();
            $tz_start = $tz_start->getName();
            $tz_start_old = $old['start']->getTimezone();
            $tz_start_old = $tz_start_old->getName();
            $tz_start = new DateTimeZone($tz_start);
            $tz_start_old = new DateTimeZone($tz_start_old);
            $transition = $tz_start->getTransitions($unix_start);
            $transition_old = $tz_start_old->getTransitions($unix_start_old);
            if ($transition[0]['isdst'] != $transition_old[0]['isdst']) {
              if ($transition[0]['isdst']) {
                $diff = $diff + 3600;
              }
              else {
                $diff = $diff - 3600;
              }
            }
            $this->_shift_recurrences($prop['id'], $diff);
          default:
            $success = $this->_update_task($prop, false);
            return $success;
      }
    }
    else {
      $success = $this->_update_task($prop);
      
      if ($recurrence) {
        $prop['recurrence'] = $recurrence;
      }

      $this->_update_recurrences($prop);

      return $success;
    }
    
    return false;
  }
  /**
   * Update task in database
   *
   * @param array Hash array with task properties
   * @return boolean success true of false
   */
  private function _update_task($prop)
  {
    if (!$prop['recurrence_id']) {
      $prop['recurrence_id'] = 0;
    }

    if (isset($prop['children_detach']) && $prop['children_detach'] == 1) { // detach children from parent
      $prop['parent_id'] = false;
    }

    $prop['complete'] = $prop['complete'] ? $prop['complete'] : 0;
    if ($prop['complete'] > 1) {
      $prop['complete'] = round($prop['complete'] / 100);
    }

    $sql_set = array();
    foreach (array('recurrence_id', 'title', 'description', 'flagged', 'complete') as $col) {
      if (isset($prop[$col]))
        $sql_set[] = $this->rc->db->quote_identifier($col) . '=' . $this->rc->db->quote($prop[$col]);
    }
    foreach (array('parent_id', 'uid', 'date', 'time', 'startdate', 'starttime', 'alarms', 'recurrence', 'status', 'exception', 'exdate') as $col) {
      if (isset($prop[$col]))
        $sql_set[] = $this->rc->db->quote_identifier($col) . '=' . (empty($prop[$col]) ? 'NULL' : $this->rc->db->quote($prop[$col]));
    }
    if (isset($prop['tags']))
      $sql_set[] = $this->rc->db->quote_identifier('tags') . '=' . $this->rc->db->quote(join(',', (array)$prop['tags']));

   if (isset($prop['date']) || isset($prop['time']) || isset($prop['alarms'])) {
      $notify_at = $this->_get_notification($prop);
      $sql_set[] = $this->rc->db->quote_identifier('notify') . '=' . (empty($notify_at) ? 'NULL' : $this->rc->db->quote($notify_at));
    }

    // moved from another list
    if ($prop['_fromlist'] && ($newlist = $prop['list'])) {
      $sql_set[] = 'tasklist_id=' . $this->rc->db->quote($newlist);
    }

    if (isset($prop['changed']) && is_a($prop['changed'], 'DateTime')) {
      $ts = date(self::DB_DATE_FORMAT, $prop['changed']->format('U'));
    }
    else {
      $ts = date(self::DB_DATE_FORMAT);
    }

    $result = $this->rc->db->query(sprintf(
        "UPDATE " . $this->db_tasks . "
        SET changed = ? %s
        WHERE task_id = ?
        AND tasklist_id IN (%s)",
        ($sql_set ? ', ' . join(', ', $sql_set) : ''),
        $this->list_ids
      ),
      $ts,
      $prop['id']
    );

    return $this->rc->db->affected_rows($result) ? true : false;
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

    if ($prop['mode'] == 2) {
      $master = $this->get_master(array('id' => $prop['parent_id'] ? $prop['parent_id'] : $prop['id']));
      $start = $prop['startdate'] . ' ' . ($prop['starttime'] ? ($prop['starttime'] . ':00') : '00:00:00');
      $master['recurrence']['EXDATE'][$start] = new DateTime($start, $this->plugin->timezone);
      $recurrence = $master['recurrence'];
      $master['recurrence'] = $this->serialize_recurrence($master['recurrence']);
      $success = $this->_update_task($master);
      $master['recurrence'] = $recurrence;
      $this->_update_recurrences($master);
    }
    else if ($prop['mode'] == 3) {
      $master = $this->get_master(array('id' => $prop['parent_id'] ? $prop['parent_id'] : $prop['id']));
      $success = $this->_delete_task($master, $force, false);
    }
    else if ($prop['mode'] == 4) {
      $master = $this->get_master(array('id' => $prop['parent_id'] ? $prop['parent_id'] : $prop['id']));
      $success = $this->_delete_task($master, $force, true);
    }
    else if ($prop['mode'] == 1) {
      $success = $this->_delete_task($prop, $force, true);
    }
    else if ($prop['mode'] == 0) {
      $success = $this->_delete_task($prop, $force, false);
    }

    
    return $success;
  }
  
  /**
   * Remove a single task from the database
   *
   * @param array   Hash array with task properties
   * @param boolean Remove record irreversible
   * @param boolean Remove subtasks;
   * @return boolean true on success, false on error
   */
  private function _delete_task($prop, $force = true, $delete_subtasks = true)
  {
    $task_id = (int) $prop['id'];
    
    $ids = (array) $this->get_childs($prop, true);
    array_push($ids, $task_id);

    if ($task_id && $force) {
      foreach ($ids as $child_id) {
        if ($delete_subtasks) {
          $query = $this->rc->db->query(
            "DELETE FROM " . $this->db_tasks ."
            WHERE parent_id LIKE ?
            AND tasklist_id IN(" . $this->list_ids . ")",
            $task_id . '%'
          );
        }
        else {
          $query = $this->rc->db->query(
            "UPDATE " . $this->db_tasks ."
            SET parent_id = ?
            WHERE parent_id LIKE ?
            AND tasklist_id IN(" . $this->list_ids . ")",
            0,
            $task_id . '%'
          );
        }
      }
      $query = $this->rc->db->query(
        "DELETE FROM " . $this->db_tasks . "
        WHERE (task_id = ? OR recurrence_id = ?)
        AND tasklist_id IN (" . $this->list_ids . ")",
        $task_id,
        $task_id
      );
    }
    else if ($task_id) {
      foreach ($ids as $child_id) {
        if ($delete_subtasks) {
          $query = $this->rc->db->query(
            "UPDATE " . $this->db_tasks ."
            SET del = ?
            WHERE parent_id = ?
            AND tasklist_id IN(" . $this->list_ids . ")",
            1,
            $task_id
          );
        }
        else {
          $query = $this->rc->db->query(
            "UPDATE " . $this->db_tasks ."
            SET parent_id = ?
            WHERE task_id = ?
            AND tasklist_id IN(" . $this->list_ids . ")",
            0,
            $task_id
          );
        }
        $query = $this->rc->db->query(sprintf(
            "UPDATE " . $this->db_tasks . "
            SET changed = %s, del = 1
            WHERE (task_id = ? OR recurrence_id = ?)
            AND tasklist_id IN (%s)",
            $this->rc->db->now(),
            $this->list_ids
          ),
          $task_id,
          $task_id
        );
      }
    }

    return $this->rc->db->affected_rows($query);
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
    $query = $this->rc->db->query(sprintf(
        "UPDATE " . $this->db_tasks . "
        SET changed=%s, del=0
        WHERE task_id=?
        AND   tasklist_id IN (%s)",
        $this->rc->db->now(),
        $this->list_ids
      ),
      $prop['id']
    );

    return $this->rc->db->affected_rows($query);
  }
    
    /**
     * Update RECURRENCE-ID (exception) and EXDATE (exdate)
     */
    private function _update_recurrences($task, $update_changed = false)
    {
      if (empty($this->lists)) {
        return;
      }
      
      $task['recurrence'] = is_string($task['recurrence']) ? $this->unserialize_recurrence($task['recurrence']) : array();
      
      // mark existing recurrence exceptions for deletion
      $this->rc->db->query(
        "UPDATE " . $this->db_tasks . "
        SET del = ? WHERE recurrence_id = ?
        AND tasklist_id IN (" . $this->list_ids . ")",
        1,
        $task['id']
      );

      $ts_changed = date(self::DB_DATE_FORMAT);
      $ts_created = $ts_changed;

      if ($task['recurrence']) {
        if (is_array($task['recurrence']['EXCEPTIONS'])) {
          $exceptions = $task['recurrence']['EXCEPTIONS'];
          foreach ($exceptions as $exception) {
            if (is_a($exception['start'], 'DateTime')) {
              $startdate = $exception['start']->format('Y-m-d');
              $starttime = $exception['start']->format('H:i');
              $timezone = $exception['start']->getTimezone();
            }
            else if($exception['startdate']) {
              $startdate = $exception['startdate'];
              $starttime = $exception['starttime'];
              $timezone = $this->plugin->timezone;
            }
            else {
              $startdate = null;
              $starttime = null;
            }
            if (is_a($exception['due'], 'DateTime')) {
              $date = $exception['due']->format('Y-m-d');
              $time = $exception['due']->format('H:i');
              $timezone = $exception['start']->getTimezone();
            }
            else if($exception['date']) {
              $date = $exception['date'];
              $time = $exception['time'];
              $timezone = $this->plugin->timezone;
            }
            else {
              $date = null;
              $time = null;
            }
            if ($starttime == '00:00') {
              $starttime = null;
            }
            if ($time == '00:00') {
              $time = null;
            }
            $notify_at = $this->_get_notification($exception);
            if (is_array($exception['recurrence_date'])) {
              $exception['recurrence_date'] = new DateTime($exception['recurrence_date']['date'], new DateTimezone($exception['recurrence_date']['timezone'] ? $exception['recurrence_date']['timezone'] : 'GMT'));
            }
            $complete = $exception['complete'] ? $exception['complete'] : 0;
            if ($complete > 1) {
              $complete = round($complete / 100);
            }
            $result = $this->rc->db->query(
              "SELECT task_id FROM " . $this->db_tasks . "
              WHERE recurrence_id = ? AND exception = ?",
              $task['id'],
              $exception_date
            );
            $existing = $this->rc->db->fetch_assoc($result);
            if (is_array($existing)) {
              $this->rc->db->query(
                "UPDATE " . $this->db_tasks ."
                SET uid = ?, parent_id = ?, changed = ?, title = ?, date = ?, time = ?, startdate = ?, starttime = ?, complete = ?, status = ?, flagged = ?, description = ?, tags = ?, alarms = ?, recurrence = ?, exception = ?, notify = ?, del = ?
                WHERE task_id = ? and tasklist_id = ?",
                $exception['uid'],
                $task['parent_id'] ? $task['parent_id'] : $task['id'],
                $ts_changed,
                $exception['title'],
                $date,
                $time,
                $startdate,
                $starttime,
                $complete,
                $exception['status'] ? $exception['status'] : '',
                $exception['priority'] ? $exception['priority'] : ($exception['flagged'] ? $exception['flagged'] : 0),
                strval($exception['description']),
                join(',', (array)$exception['categories']),
                $exception['alarms'],
                null,
                date(self::DB_DATE_FORMAT, $exception['recurrence_date']->format('U')),
                $notify_at,
                0,
                $existing['task_id'],
                $task['list']
              );
            }
            else {
              $this->rc->db->query(
                "INSERT INTO " . $this->db_tasks . "
                (tasklist_id, uid, parent_id, recurrence_id, created, changed, title, date, time, startdate, starttime, complete, status, flagged, description, tags, alarms, recurrence, exception, notify, del)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $task['list'],
                $exception['uid'],
                $task['parent_id'] ? $task['parent_id'] : $task['id'],
                $task['id'],
                $ts_created,
                $ts_changed,
                $exception['title'],
                $date,
                $time,
                $startdate,
                $starttime,
                $complete,
                $exception['status'] ? $exception['status'] : '',
                $exception['priority'] ? $exception['priority'] : ($exception['flagged'] ? $exception['flagged'] : 0),
                strval($exception['description']),
                join(',', (array)$exception['categories']),
                $exception['alarms'],
                null,
                date(self::DB_DATE_FORMAT, $exception['recurrence_date']->format('U')),
                $notify_at,
                0
              );
            }
          }
        }
        if (is_array($task['recurrence']['EXDATE'])) {
          $exdates = $task['recurrence']['EXDATE'];
          foreach ($exdates as $exdate) {
            if (is_array($exdate)) {
              $exdate = strtotime($exdate['date']);
            }
            else {
              $exdate = $exdate->format('U');
            }
            $exdate = new DateTime(date('Y-m-d H:i:s', $exdate), $timezone);
            $result = $this->rc->db->query(
              "SELECT task_id FROM " . $this->db_tasks . "
              WHERE recurrence_id = ? AND exdate = ? AND tasklist_id = ?",
              $task['id'],
              $exdate->format(self::DB_DATE_FORMAT),
              $task['list']
            );
            $exists = $this->rc->db->fetch_assoc($result);
            if (is_array($exists)) {
              $this->rc->db->query(
                "UPDATE " . $this->db_tasks . "
                SET exdate = ?, changed = ?, del = ?
                WHERE task_id = ? AND tasklist_id = ?",
                $exdate->format(self::DB_DATE_FORMAT),
                $ts_changed,
                0,
                $exists['task_id'],
                $task['list']
              );
            }
            else {
              $this->rc->db->query(
                "INSERT INTO " . $this->db_tasks . "
                (tasklist_id, parent_id, recurrence_id, exdate, created, changed, uid, date, time, startdate, starttime, title, description, tags, flagged, alarms, notify, del)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $task['list'],
                $task['id'],
                $task['id'],
                $exdate->format(self::DB_DATE_FORMAT),
                $ts_created,
                $ts_changed,
                strval($task['uid']),
                $date,
                $time,
                $startdate,
                $starttime,
                '',
                '',
                '',
                0,
                null,
                null,
                0
              );
            }
          }
        }
      }
      
      // clear remaining exceptions
      $this->rc->db->query(
        "DELETE FROM " . $this->db_tasks . "
        WHERE del=?
        AND tasklist_id IN (" . $this->list_ids . ")",
        1
      );
      
      if ($update_changed) {
        $this->rc->db->query(
          "UPDATE " . $this->db_tasks . "
            SET changed = ? WHERE task_id = ?",
            $ts_changed,
            $task['id']
        );
      }

      return true;
    }

    /**
     * Calculate recurrences and return clones
     */
    private function _get_recurrences($task, $has_parent)
    {
      if (!$task['startdate']) {
        return $task;
      }
      
      if(serialize(func_get_args()) == $_SESSION['tasks_recurrences_request']){
        unset($_SESSION['tasks_recurrences_request']);
        return $_SESSION['tasks_recurrences_cache'];
      }
      
      $recurrences = array();
      
      $start = $task['startdate'] . ($task['starttime'] ? (' ' . $task['starttime']) : '');
      $task['start'] = new DateTime($start, $this->plugin->timezone);

      if ($task['date']) {
        $due = $task['date'] . ($task['time'] ? (' ' . $task['time'] . ':00') : '');
        $due = new DateTime($due);
        $delta = $task['start']->diff($due);
      }

      $engine = libcalendaring::get_recurrence();
      $rrule = $this->unserialize_recurrence($task['recurrence']);

      // compute the next occurrence of date/time attributes

      if (!empty($rrule['EXDATE']) && is_array($rrule['EXDATE'])) {
        /*
        foreach ($rrule['EXDATE'] as $idx => $exdate) {
          if (is_array($exdate)) {
            $rrule['EXDATE'][$idx] = new DateTime($exdate['date'], new DateTimezone($exdate['timezone']));
          }
        }
        */
        unset($rrule['EXDATE']); // we have our own exdate records
                                  // Horde seems not to work properly if start and exdate have different timezones;
      }

      $engine->init($rrule, $task['start']->format('Y-m-d H:i:s'));
      $clones = array();
      while ($next = $engine->next()) {
        $next_formatted = $next->format('Y-m-d H:i');
        unset($task['recurrence'], $task['start']);
        $clone = $task;
        $clone['start'] = new DateTime($next_formatted);
        if ($task['date']) {
          $clone['due'] = $clone['start']->add($delta);
          $due = explode(' ', $clone['due']->format('Y-m-d H:i'));
          $clone['date'] = $due[0];
          $clone['time'] = $due[1];
        }
        $clone_start = explode(' ', $next_formatted);
        if ($task['startdate']) {
          $clone['startdate'] = $clone_start[0];
          $clone['starttime'] = $clone_start[1];
        }
        $clone['task_id'] .= '-' . md5($next_formatted);
        if ($has_parent) {
          $clone['parent_id'] = $task['task_id'];
        }
        if ($clone['startdate'] > date('Y-m-d')) {
          break;
        }
        // Check RECURRENCE-ID (internal exception) and EXDATE (internal exdate)
        $result = $this->rc->db->limitquery(
          'SELECT task_id FROM ' . $this->db_tasks . ' WHERE (exception = ? OR exdate = ?) AND tasklist_id = ? AND recurrence_id = ?',
          0,
          1,
          $clone['start']->format(self::DB_DATE_FORMAT),
          $clone['start']->format(self::DB_DATE_FORMAT),
          $task['tasklist_id'],
          $task['task_id']
        );
        $exception = $this->rc->db->fetch_assoc($result);
        if (!is_array($exception)) {
          unset($clone['due'], $clone['start']);
          $clone['isclone'] = true;
          $clones[] = $this->_read_postprocess($clone);
        }
      }

      $_SESSION['tasks_recurrences_request'] = serialize(func_get_args());
      $_SESSION['tasks_recurrences_cache'] = $clones;

      return $clones;
    }
    
    /**
     * Check if an exception (RECURRENCE-ID) or exdate (EXDATE) for a given task exists
     *
     * @param array Hash array with task properties
     * @return boolean success (true or false)
     */
    public function _is_exception($task)
    {
      if (!$task['startdate']) {
        return false;
      }
      
      $task_id = $task['id'] ? $task['id'] : $task['task_id'];
      $list_id = $task['list'] ? $task['list'] : $task['tasklist_id'];
      
      $result = $this->rc->db->limitquery(
        'SELECT task_id FROM ' . $this->db_tasks . ' WHERE (exception = ? OR exdate = ?) AND tasklist_id = ? AND recurrence_id = ?',
        0,
        1,
        $task['startdate'] . ($task['starttime'] ? (' ' . $task['starttime'] . ':00') : ' 00:00:00'),
        $task['startdate'] . ($task['starttime'] ? (' ' . $task['starttime'] . ':00') : ' 00:00:00'),
        $list_id,
        $task_id
      );
      if ($this->rc->db->fetch_assoc($result)) {
        return true;
      }
      else {
        return false;
      }
    }
    
  /**
   * Shift exceptions
   *
   * @param integer task database identifier
   * @param integer time difference in seconds
   */
  private function _shift_recurrences($recurrence_id, $diff)
  {
    $result = $this->rc->db->query(
      "SELECT task_id, exception, exdate FROM " . $this->db_tasks . "
        WHERE recurrence_id = ?",
        $recurrence_id
    );
    
    $changed = date(self::DB_DATE_FORMAT);
    while ($result && $record = $this->rc->db->fetch_assoc($result)) {
      if ($record['exception']) {
        $new_date = date(self::DB_DATE_FORMAT, strtotime($record['exception']) + $diff);
        $col  = 'exception';
        $col2 = 'exdate';
      }
      else if ($record['exdate']) {
        $new_date = date(self::DB_DATE_FORMAT, strtotime($record['exdate']) + $diff);
        $col  = 'exdate';
        $col2 = 'exception';
      }
      $result2 = $this->rc->db->query(
        "UPDATE " . $this->db_tasks . "
          SET $col = ?, changed = ? WHERE event_id = ? AND recurrence_id = ? AND $col2 IS NULL",
          $new_date,
          $changed,
          $record['task_id'],
          $recurrence_id
      );
    }
  }

  /**
   * Remove future exceptions
   *
   * @param integer recurrence database identifier
   * @param string date (self::DB_DATE_FORMAT)
   */
    private function _clear_recurrences($recurrence_id, $date = null)
    {
      $this->rc->db->query(
        "DELETE FROM " . $this->db_tasks . "
          WHERE recurrence_id = ? AND (exception >= ? OR exdate >= ?)",
          $recurrence_id,
          $date,
          $date
      );
    }
  
    /**
     * Compute absolute time to notify the user
     */
    private function _get_notification($task)
    {
        if ($task['alarms']) {
            $start = $task['startdate'] . ($task['starttime'] ? (' ' . $task['starttime']) : '');
            $task['start'] = new DateTime($start, $this->plugin->timezone);
            if ($task['recurrence']) {
                $engine = libcalendaring::get_recurrence();
                $rrule = $this->unserialize_recurrence($task['recurrence']);
                $engine->init($rrule, $task['start']->format('Y-m-d H:i:s'));
                while ($next_start = $engine->next()) {
                    $next_start->setTimezone($this->plugin->timezone);
                    if ($next_start > new DateTime()) {
                        $task['start'] = $next_start;
                        $alarm = libcalendaring::get_next_alarm($task);
                        break;
                    }
                }
            }
            else if($task['start'] > new DateTime()) {
                $alarm = libcalendaring::get_next_alarm($task);
            }
            if ($alarm['time']) {
                return date('Y-m-d H:i:s', $alarm['time']);
            }
        }

        return null;
    }


    /**
     * Helper method to serialize task recurrence properties
     */
    private function serialize_recurrence($recurrence)
    {
        foreach ((array)$recurrence as $k => $val) {
            if ($val instanceof DateTime) {
                $recurrence[$k] = '@' . $val->format('c');
            }
        }

        return $recurrence ? json_encode($recurrence) : null;
    }

    /**
     * Helper method to decode a serialized task recurrence struct
     */
    private function unserialize_recurrence($ser)
    {
        if (strlen($ser)) {
            $recurrence = json_decode($ser, true);
            foreach ((array)$recurrence as $k => $val) {
                if ($val[0] == '@') {
                    try {
                        $recurrence[$k] = new DateTime(substr($val, 1));
                    }
                    catch (Exception $e) {
                        unset($recurrence[$k]);
                    }
                }
            }
        }
        else {
            $recurrence = '';
        }

        return $recurrence;
    }
    
    /**
     * Increase SEQUENCE (RFC5545 3.8.7.4)
     * @param array Hash array with task properties
     * @return array Hash array with task properties
     */
    private function _increase_sequence($task)
    {
      return $task;
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
}