<?php

/**
 * Roundcube CardDAV addressbook extension
 *
 * @author Roland 'rosali' Liebl
 **/
 
/** 
 * Forked from
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5.1
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */
class carddav_addressbook extends rcube_addressbook
{
  protected $user_id;
  protected $rcpassword;
  protected $db;
  protected $db_name = 'carddav_contacts';
  protected $db_groups = 'carddav_contactgroups';
  protected $db_groupmembers = 'carddav_contactgroupmembers';
  protected $db_servers = 'carddav_server';

  private $filter;
  private $result;
  private $name;
  private $carddav_server_id = false;
  private $counter = 0;
  private $table_cols = array('name', 'firstname', 'surname', 'words', 'email', 'carddav_contact_id');
  private $fulltext_cols = array('name', 'firstname', 'surname', 'middlename', 'nickname',
    'jobtitle', 'organization', 'department', 'maidenname', 'email', 'phone',
    'address', 'street', 'locality', 'zipcode', 'region', 'country', 'website', 'im', 'notes');
  private $cache;

  public $rc;
  public $primary_key = 'carddav_contact_id';
  public $undelete = true;
  public $readonly = false;
  public $addressbook = false;
  public $groups = true;
  public $group_id = 0;
  public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'email', 'photo');
  public $date_cols = array('birthday', 'anniversary');
  
  const SEPARATOR = ',';

  /**
  * Init CardDAV addressbook
  *
  * @param	string		$carddav_server_id		Translated addressbook name
  * @param	integer		$name					CardDAV server id
  * @return	void
  */
  function __construct($carddav_server_id, $name, $readonly, $addressbook){
    $this->ready = true;
    $this->name = $name;
    $this->carddav_server_id = $carddav_server_id;
    $this->readonly = $readonly;
    $this->rc = rcube::get_instance();
    $this->db = $this->rc->db;
    $this->user_id = $this->rc->user->ID;
    $password = $_SESSION['default_account_password'] ? $_SESSION['default_account_password'] : $_SESSION['password'];
    $this->rcpassword = $password;
    $this->addressbook = $addressbook;
    if(class_exists('carddav')){
      $this->coltypes = carddav::carddav_coltypes();
    }
  }

  /**
   * Returns addressbook name
   */
  function get_name(){
    return $this->name;
  }

  /**
   * Save a search string for future listings
   *
   * @param string SQL params to use in listing method
   */
  function set_search_set($filter){
    $this->filter = $filter;
    $this->cache = null;
  }
  
  /**
   * Getter for saved search properties
   *
   * @return mixed Search properties used by this class
   */
  function get_search_set(){
    return $this->filter;
  }
  
  /**
   * Setter for the current group
   * (empty, has to be re-implemented by extending class)
   */
  function set_group($gid){
    $this->group_id = $gid;
    $this->cache = null;
  }

  /**
   * Reset all saved results and search parameters
   */
  function reset(){
    $this->result = null;
    $this->filter = null;
    $this->cache = null;
  }
  
  /**
   * List all active contact groups of this source
   *
   * @param string  Search string to match group name
   * @param int     Matching mode:
   *                0 - partial (*abc*),
   *                1 - strict (=),
   *                2 - prefix (abc*)
   *
   * @return array  Indexed list of contact groups, each a hash array
   */
  function list_groups($search = null, $mode = 0){
    $results = array();
    if(!$this->groups)
      return $results;

    if($search){
      switch(intval($mode)){
        case 1:
          $sql_filter = $this->db->ilike('name', $search);
          break;
        case 2:
          $sql_filter = $this->db->ilike('name', $search . '%');
          break;
        default:
          $sql_filter = $this->db->ilike('name', '%' . $search . '%');
      }
      $sql_filter = " AND $sql_filter";
    }
    
    $sql_result = $this->db->query(
      "SELECT * FROM ".get_table_name($this->db_groups).
      " WHERE del<>1".
      " AND user_id=?".
      " AND addressbook=?".
      $sql_filter.
      " ORDER BY name",
      $this->user_id,
      $this->carddav_server_id);

    while($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))){
      $sql_arr['ID'] = $sql_arr['contactgroup_id'];
      $results[]     = $sql_arr;
    }
    return $results;
  }
  
  /**
   * Get group properties such as name and email address(es)
   *
   * @param string Group identifier
   * @return array Group properties as hash array
   */
  function get_group($group_id){
    $sql_result = $this->db->query(
      "SELECT * FROM ".get_table_name($this->db_groups).
      " WHERE del<>1".
      " AND contactgroup_id=?".
      " AND user_id=?".
      " AND addressbook=?",
      $group_id,
      $this->user_id,
      $this->carddav_server_id
    );

    if($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
      $sql_arr['ID'] = $sql_arr['contactgroup_id'];
      return $sql_arr;
    }

    return null;
  }
  
  /**
   * List the current set of contact records
   *
   * @param  array   List of cols to show, Null means all
   * @param  int     Only return this number of records, use negative values for tail
   * @param  boolean True to skip the count query (select only)
   * @return array  Indexed list of contact records, each a hash array
   */
  function list_records($cols=null, $subset=0, $nocount=false){
    if($nocount || $this->list_page <= 1){
      // create dummy result, we don't need a count now
      $this->result = new rcube_result_set();
    }
    else{
      // count all records
      $this->result = $this->count();
    }

    $start_row = $subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first;
    $length = $subset != 0 ? abs($subset) : $this->page_size;

    if($this->group_id)
      $join = " LEFT JOIN ".$this->db->table_name($this->db_groupmembers)." AS m".
        " ON (m.contact_id = c.".$this->primary_key.")";

    $order_col = (in_array($this->sort_col, $this->table_cols) ? $this->sort_col : 'name');
    $order_cols = array('c.'.$order_col);
    if($order_col == 'firstname')
      $order_cols[] = 'c.surname';
    else if($order_col == 'surname')
      $order_cols[] = 'c.firstname';
    if($order_col != 'name')
      $order_cols[] = 'c.name';
    $order_cols[] = 'c.email';
    $sql_result = $this->db->limitquery(
      "SELECT * FROM ".$this->db->table_name($this->db_name)." AS c" .
      $join .
      " WHERE c.user_id=? AND c.carddav_server_id=?" .
        ($this->group_id ? " AND m.contactgroup_id=?" : "").
        ($this->filter ? " AND (".$this->filter.")" : "") .
      " ORDER BY ". $this->db->concat($order_cols) .
      " " . $this->sort_order,
      $start_row,
      $length,
      $this->user_id,
      $this->carddav_server_id,
      $this->group_id);

    // determine whether we have to parse the vcard or if only db cols are requested
    $read_vcard = !$cols || count(array_intersect($cols, $this->table_cols)) < count($cols);

    while($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))){
      $sql_arr['ID'] = $sql_arr[$this->primary_key];

      if($read_vcard)
        $sql_arr = $this->convert_db_data($sql_arr);
      else{
        $sql_arr['email'] = explode(self::SEPARATOR, $sql_arr['email']);
        $sql_arr['email'] = array_map('trim', $sql_arr['email']);
      }

      $this->result->add($sql_arr);
    }

    $cnt = count($this->result->records);

    // update counter
    if($nocount)
      $this->result->count = $cnt;
    else if($this->list_page <= 1){
      if($cnt < $this->page_size && $subset == 0)
        $this->result->count = $cnt;
      else if(isset($this->cache['count']))
        $this->result->count = $this->cache['count'];
      else
        $this->result->count = $this->_count();
    }

    return $this->result;
  }

  /**
   * Search contacts
   *
   * @param mixed   $fields   The field name of array of field names to search in
   * @param mixed   $value    Search value (or array of values when $fields is array)
   * @param int     $mode     Matching mode:
   *                          0 - partial (*abc*),
   *                          1 - strict (=),
   *                          2 - prefix (abc*)
   * @param boolean $select   True if results are requested, False if count only
   * @param boolean $nocount  True to skip the count query (select only)
   * @param array   $required List of fields that cannot be empty
   *
   * @return object rcube_result_set Contact records and 'count' value
   */
  function search($fields, $value, $mode=0, $select=true, $nocount=false, $required=array()){
    if(!is_array($fields))
      $fields = array($fields);
    if(!is_array($required) && !empty($required))
      $required = array($required);
    if($mode == 0 && rcube::get_instance()->config->get('carddav_words_col_autocomplete', rcube::get_instance()->config->get('words_col_autocomplete', false))){
      $fields = array_merge($fields, array('words'));
    }
    $where = $and_where = array();
    $mode = intval($mode);
    $WS = ' ';
    $AS = self::SEPARATOR;

    foreach($fields as $idx => $col){
      // direct ID search
      if($col == 'ID' || $col == $this->primary_key){
        $ids     = !is_array($value) ? explode(self::SEPARATOR, $value) : $value;
        $ids     = $this->db->array2list($ids, 'integer');
        $where[] = 'c.' . $this->primary_key.' IN ('.$ids.')';
        continue;
      }
      // fulltext search in all fields
      else if($col == '*'){
        $words = array();
        foreach(explode($WS, rcube_utils::normalize_string($value)) as $word){
          switch($mode){
            case 1: // strict
              $words[] = '(' . $this->db->ilike('words', $word . '%')
                . ' OR ' . $this->db->ilike('words', '%' . $WS . $word . $WS . '%')
                . ' OR ' . $this->db->ilike('words', '%' . $WS . $word) . ')';
              break;
            case 2: // prefix
              $words[] = '(' . $this->db->ilike('words', $word . '%')
                . ' OR ' . $this->db->ilike('words', '%' . $WS . $word . '%') . ')';
              break;
            default: // partial
              $words[] = $this->db->ilike('words', '%' . $word . '%');
          }
        }
        $where[] = '(' . join(' AND ', $words) . ')';
      }
      else{
        $val = is_array($value) ? $value[$idx] : $value;
        // table column
        if(in_array($col, $this->table_cols)){
          switch($mode){
            case 1: // strict
              $where[] = '(' . $this->db->quote_identifier($col) . ' = ' . $this->db->quote($val)
                . ' OR ' . $this->db->ilike($col, $val . $AS . '%')
                . ' OR ' . $this->db->ilike($col, '%' . $AS . $val . $AS . '%')
                . ' OR ' . $this->db->ilike($col, '%' . $AS . $val) . ')';
              break;
            case 2: // prefix
              $where[] = '(' . $this->db->ilike($col, $val . '%')
                . ' OR ' . $this->db->ilike($col, $AS . $val . '%') . ')';
              break;
            default: // partial
              $where[] = $this->db->ilike($col, '%' . $val . '%');
          }
        }
        // vCard field
        else{
          if(in_array($col, $this->fulltext_cols)){
            foreach(rcube_utils::normalize_string($val, true) as $word){
              switch($mode){
                case 1: // strict
                  $words[] = '(' . $this->db->ilike('words', $word . $WS . '%')
                    . ' OR ' . $this->db->ilike('words', '%' . $AS . $word . $WS .'%')
                    . ' OR ' . $this->db->ilike('words', '%' . $AS . $word) . ')';
                  break;
                case 2: // prefix
                  $words[] = '(' . $this->db->ilike('words', $word . '%')
                    . ' OR ' . $this->db->ilike('words', $AS . $word . '%') . ')';
                  break;
                default: // partial
                  $words[] = $this->db->ilike('words', '%' . $word . '%');
              }
            }
            $where[] = '(' . join(' AND ', $words) . ')';
          }
          if(is_array($value))
            $post_search[$col] = mb_strtolower($val);
        }
      }
    }

    foreach(array_intersect($required, $this->table_cols) as $col){
      $and_where[] = $this->db->quote_identifier($col).' <> '.$this->db->quote('');
    }

    if(!empty($where)){
      // use AND operator for advanced searches
      $where = join(is_array($value) ? ' AND ' : ' OR ', $where);
    }

    if(!empty($and_where))
      $where = ($where ? "($where) AND " : '') . join(' AND ', $and_where);

    // Post-searching in vCard data fields
    // we will search in all records and then build a where clause for their IDs
    if(!empty($post_search)){
      $ids = array(0);
      // build key name regexp
      $regexp = '/^(' . implode(array_keys($post_search), '|') . ')(?:.*)$/';
      // use initial WHERE clause, to limit records number if possible
      if(!empty($where))
        $this->set_search_set($where);

      // count result pages
      $cnt   = $this->count();
      $pages = ceil($cnt / $this->page_size);
      $scnt  = count($post_search);

      // get (paged) result
      for($i=0; $i<$pages; $i++){
        $this->list_records(null, $i, true);
        while($row = $this->result->next()){
          $id    = $row[$this->primary_key];
          $found = array();
          foreach(preg_grep($regexp, array_keys($row)) as $col){
            $pos     = strpos($col, ':');
            $colname = $pos ? substr($col, 0, $pos) : $col;
            $search  = $post_search[$colname];
            foreach((array)$row[$col] as $value){
              if($this->compare_search_value($colname, $value, $search, $mode)){
                $found[$colname] = true;
                break 2;
              }
            }
          }
          // all fields match
          if(count($found) >= $scnt){
            $ids[] = $id;
          }
        }
      }

      // build WHERE clause
      $ids = $this->db->array2list($ids, 'integer');
      $where = 'c.' . $this->primary_key.' IN ('.$ids.')';
      // reset counter
      unset($this->cache['count']);

      // when we know we have an empty result
      if($ids == '0'){
        $this->set_search_set($where);
        return ($this->result = new rcube_result_set(0, 0));
      }
    }
    if(!empty($where)){
      $this->set_search_set($where);
      if($select)
        $this->list_records(null, 0, $nocount);
      else
        $this->result = $this->count();
    }
    return $this->result;
  }
  
  /**
   * Count number of available contacts in database
   *
   * @return rcube_result_set Result object
   */
  function count(){
    $count = isset($this->cache['count']) ? $this->cache['count'] : $this->_count();

    return new rcube_result_set($count, ($this->list_page-1) * $this->page_size);
  }

  /**
   * Count number of available contacts in database
   *
   * @return int Contacts count
   */
  private function _count(){
    if($this->group_id)
      $join = " LEFT JOIN ".$this->db->table_name($this->db_groupmembers)." AS m".
        " ON (m.contact_id=c.".$this->primary_key.")";
    // count contacts for this user
    $sql_result = $this->db->query(
      "SELECT COUNT(c.carddav_contact_id) AS rows".
      " FROM ".$this->db->table_name($this->db_name)." AS c".
      $join.
      " WHERE c.user_id=? AND c.carddav_server_id=?".
        ($this->group_id ? " AND m.contactgroup_id=?" : "").
        ($this->filter ? " AND (".$this->filter.")" : ""),
        $this->user_id,
        $this->carddav_server_id,
        $this->group_id
      );

    $sql_arr = $this->db->fetch_assoc($sql_result);
    $this->cache['count'] = (int) $sql_arr['rows'];

    return $this->cache['count'];
  }
  
  /**
   * Return the last result set
   *
   * @return mixed Result array or NULL if nothing selected yet
   */
  function get_result(){
    return $this->result;
  }

  /**
   * Get a specific contact record
   *
   * @param mixed record identifier(s)
   * @return mixed Result object with all record fields or False if not found
   */
  function get_record($id, $assoc=false){
    // return cached result
    if($this->result && ($first = $this->result->first()) && $first[$this->primary_key] == $id)
      return $assoc ? $first : $this->result;

    $this->db->query(
      "SELECT * FROM ".$this->db->table_name($this->db_name).
      " WHERE " . $this->primary_key . "=?".
          " AND user_id=?",
      $id,
      $this->user_id
    );

    if($sql_arr = $this->db->fetch_assoc()){
      $record = $this->convert_db_data($sql_arr);
      $this->result = new rcube_result_set(1);
      $this->result->add($record);
    }

    return $assoc && $record ? $record : $this->result;
  }
  
  /**
   * Get group assignments of a specific contact record
   *
   * @param mixed Record identifier
   * @return array List of assigned groups as ID=>Name pairs
   */
  function get_record_groups($id){
    $results = array();

    if(!$this->groups)
      return $results;

    $sql_result = $this->db->query(
      "SELECT cgm.contactgroup_id, cg.name FROM " . $this->db->table_name($this->db_groupmembers) . " AS cgm" .
      " LEFT JOIN " . $this->db->table_name($this->db_groups) . " AS cg ON (cgm.contactgroup_id = cg.contactgroup_id AND cg.del<>1)" .
      " WHERE cgm.contact_id=?",
      $id
    );
    while($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))){
      $results[$sql_arr['contactgroup_id']] = $sql_arr['name'];
    }

    return $results;
  }
  
  /**
   * Check the given data before saving.
   * If input not valid, the message to display can be fetched using get_error()
   *
   * @param array Assoziative array with data to save
   * @param boolean Try to fix/complete record automatically
   * @return boolean True if input is valid, False if not.
   */
  function validate(&$save_data, $autofix = false){
    // validate e-mail addresses
    $valid = parent::validate($save_data, $autofix);

    // require at least one email address or a name
    // https://code.google.com/p/myroundcube/issues/detail?id=534
    /*
    if($valid && !strlen($save_data['firstname'].$save_data['surname'].$save_data['name']) && !array_filter($this->get_col_values('email', $save_data, true))){
      $this->set_error(self::ERROR_VALIDATE, 'noemailwarning');
      $valid = false;
    }
     */
    return $valid;
  }
  
  /**
   * Create a new contact record
   *
   * @param array Associative array with save data
   * @return integer|boolean The created record ID on success, False on error
   */
  function insert($save_data, $check = false){
    $rcmail = rcube::get_instance();

    if(!is_array($save_data)){
      return false;
    }
    
    if($gid = get_input_value('_gid', RCUBE_INPUT_POST)){
      $sql = 'SELECT * FROM ' . get_table_name($this->db_groups) . ' WHERE contactgroup_id=? AND del=?';
      $result = $this->rc->db->query($sql, $gid, 0);
      $result = $this->rc->db->fetch_assoc($result);
      if(is_array($result)){
        $save_data['groups'] = $result['name'];
      }
    }
    
    $insert_id = $existing = false;

    if($check){
      foreach($save_data as $col => $values){
        if(strpos($col, 'email') === 0){
          foreach((array)$values as $email){
            if($existing = $this->search('email', $email, false, false))
              break 2;
          }
        }
      }
    }

    $save_data = $this->convert_save_data($save_data);
    if(!$existing->count){
      $insert_id = $this->carddav_add($save_data['vcard']);
    }
    
    $this->cache = null;
    
    return $insert_id;
  }

  /**
   * Update a specific contact record
   *
   * @param mixed Record identifier
   * @param array Assoziative array with save data
   *
   * @return boolean True on success, False on error
   */
  function update($carddav_contact_id, $save_data = false){
    $record = $this->get_record($carddav_contact_id, true);
    
    if(!$save_data){
      $save_data = $record;
    }
    
    if($cid = get_input_value('_cid', RCUBE_INPUT_POST)){
      $sql = 'SELECT * FROM ' . get_table_name($this->db_groupmembers) . ' WHERE contact_id=?';
      $result = $this->rc->db->query($sql, $cid);
      $memberships = array();
      while($membership = $this->rc->db->fetch_assoc($result)){
        $memberships[] = $membership['contactgroup_id'];
      }
      if(!empty($memberships)){
        $categories = array();
        foreach($memberships as $membership){
          $sql = 'SELECT * FROM ' . get_table_name($this->db_groups) . ' WHERE contactgroup_id=? AND del=?';
          $result = $this->rc->db->query($sql, $membership, 0);
          $result = $this->rc->db->fetch_assoc($result);
          $categories[] = $result['name'];
        }
        $save_data['groups'] = implode(',', $categories);
      }
    }

    $save_data = $this->convert_save_data($save_data, $record);
    $this->result = null;
    
    return $this->carddav_update($carddav_contact_id, $save_data['vcard']);
  }

  private function convert_db_data($sql_arr){
    $record = array();
    $record['ID'] = $sql_arr[$this->primary_key];

    if($sql_arr['vcard']){
      unset($sql_arr['email']);
      $vcard = new rcube_vcard($sql_arr['vcard']);
      $record += $vcard->get_assoc() + $sql_arr;
    }
    else{
      $record += $sql_arr;
      $record['email'] = explode(self::SEPARATOR, $record['email']);
      $record['email'] = array_map('trim', $record['email']);
    }

    return $record;
  }

  private function convert_save_data($save_data, $record = array()){
    $out = array();
    $words = '';

    // copy values into vcard object
    $vcard = new rcube_vcard($record['vcard'] ? $record['vcard'] : $save_data['vcard']);
    $vcard->reset();
    foreach($save_data as $key => $values){
      list($field, $section) = explode(':', $key);
      $fulltext = in_array($field, $this->fulltext_cols);
      // avoid casting DateTime objects to array
      if(is_object($values) && is_a($values, 'DateTime')){
        $values = array(0 => $values);
      }
      foreach((array)$values as $value){
        if(isset($value))
          $vcard->set($field, $value, $section);
        if($fulltext && is_array($value))
          $words .= ' ' . rcube_utils::normalize_string(join(" ", $value));
        else if($fulltext && strlen($value) >= 3)
          $words .= ' ' . rcube_utils::normalize_string($value);
      }
    }
    $out['vcard'] = $vcard->export(false);

    foreach($this->table_cols as $col){
      $key = $col;
      if(!isset($save_data[$key]))
        $key .= ':home';
      if(isset($save_data[$key])){
        if(is_array($save_data[$key]))
          $out[$col] = join(self::SEPARATOR, $save_data[$key]);
        else
          $out[$col] = $save_data[$key];
      }
    }

    // save all e-mails in database column
    $out['email'] = join(self::SEPARATOR, $vcard->email);

    // join words for fulltext search
    $out['words'] = join(" ", array_unique(explode(" ", $words)));

    return $out;
  }
  
  /**
   * Delete one or more contact records
   *
   * @param array   Record identifiers
   * @param boolean Remove record(s) irreversible (unsupported)
   */
  function delete($carddav_contact_ids, $force = true){
    if(!is_array($carddav_contact_ids)){
      $carddav_contact_ids = explode(self::SEPARATOR, $carddav_contact_ids);
    }
    
    $this->cache = null;
    
    return $this->carddav_delete($carddav_contact_ids);
  }

  /**
   * Undelete one or more contact records
   *
   * @param array  Record identifiers
   */
  function undelete($ids){
    // not implemented
    $this->cache = null;
    
    return false;
  }
  
  /**
   * Remove all records from the database
   *
   * @param bool $with_groups Remove also groups
   *
   * @return int Number of removed records
   */
  function delete_all($with_groups = false){
    $carddav_contact_ids = array();
    $sql = 'SELECT carddav_contact_id FROM ' . get_table_name($this->db_name) . ' WHERE user_id=? AND carddav_server_id=?';
    $result = $this->db->query($sql, intval($this->user_id), $this->carddav_server_id);
    while($id = $this->db->fetch_assoc($result)){
      $carddav_contact_ids[] = $id['carddav_contact_id'];
    }
    $this->delete($carddav_contact_ids); 
    
    $this->cache = null;
    
    return count($carddav_contact_ids);
  }

  /**
   * Create a contact group with the given name
   *
   * @param string The group name
   * @return mixed False on error, array with record props in success
   */
  function create_group($name){
    if(!$name){
      return false;
    }

    $result = false;

    // make sure we have a unique name
    $name = $this->unique_groupname($name);

    $this->db->query(
      "INSERT INTO ".$this->db->table_name($this->db_groups).
      " (user_id, changed, name, addressbook)".
      " VALUES (".intval($this->user_id).", ".$this->db->now().", ".$this->db->quote($name).", ".$this->db->quote($this->carddav_server_id).")"
    );

    if($insert_id = $this->db->insert_id($this->db_groups))
      $result = array('id' => $insert_id, 'name' => $name);

    return $result;
  }

  /**
   * Delete the given group (and all linked group members)
   *
   * @param string Group identifier
   * @return boolean True on success, false if no data was changed
   */
  function delete_group($gid){
    // flag group record as deleted
    $sql_result = $this->db->query(
      "UPDATE ".get_table_name($this->db_groups).
      " SET del=1, changed=".$this->db->now().
      " WHERE contactgroup_id=?".
      " AND user_id=?",
      $gid, $this->user_id
    );
    
    $this->cache = null;
    $ret = $this->db->affected_rows();
    
    if($ret){
      $this->groups_upstream_sync($gid);
    }
    
    return $ret;
  }

  /**
   * Rename a specific contact group
   *
   * @param string Group identifier
   * @param string New name to set for this group
   * @return boolean New name on success, false if no data was changed
   */
  function rename_group($gid, $newname, &$new_gid){
    // make sure we have a unique name
    $name = $this->unique_groupname($newname);

    $sql_result = $this->db->query(
      "UPDATE ".get_table_name($this->db_groups).
      " SET name=?, changed=".$this->db->now().
      " WHERE contactgroup_id=?".
      " AND user_id=?",
      $name, $gid, $this->user_id
    );
    
    $ret = $this->db->affected_rows() ? $name : false;
    if($ret){
      $this->groups_upstream_sync($gid);
    }
    
    return $ret;
  }

  /**
   * Add the given contact records the a certain group
   *
   * @param string       Group identifier
   * @param array|string List of contact identifiers to be added
   *
   * @return int Number of contacts added
   */
  function add_to_group($group_id, $ids){
    if(!$group_id){
      $group_id = get_input_value('_gid', RCUBE_INPUT_POST);
      if(!$group_id){
        return 0;
      }
    }
    if(!is_array($ids))
      $ids = explode(self::SEPARATOR, $ids);

    $added = 0;
    $exists = array();

    // get existing assignments ...
    $sql_result = $this->db->query(
      "SELECT contact_id FROM ".get_table_name($this->db_groupmembers).
      " WHERE contactgroup_id=?".
      " AND contact_id IN (".$this->db->array2list($ids, 'integer').")",
      $group_id
    );
    while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
      $exists[] = $sql_arr['contact_id'];
    }
    // ... and remove them from the list
    $ids = array_diff($ids, $exists);

    foreach($ids as $contact_id) {
      $this->db->query(
        "INSERT INTO ".get_table_name($this->db_groupmembers).
        " (contactgroup_id, contact_id, created)".
        " VALUES (?, ?, ".$this->db->now().")",
        $group_id,
        $contact_id
      );

      if($error = $this->db->is_error())
        $this->set_error(self::ERROR_SAVING, $error);
      else
        $added++;
        $this->update($contact_id);
      }

    return $added;
  }

  /**
   * Remove the given contact records from a certain group
   *
   * @param string       Group identifier
   * @param array|string List of contact identifiers to be removed
   *
   * @return int Number of deleted group members
   */
  function remove_from_group($group_id, $ids){
    if(!is_array($ids))
      $ids = explode(self::SEPARATOR, $ids);

    $ids = $this->db->array2list($ids, 'integer');

    $sql_result = $this->db->query(
      "DELETE FROM ".get_table_name($this->db_groupmembers).
      " WHERE contactgroup_id=?".
      " AND contact_id IN ($ids)",
      $group_id
    );
    $rows = $this->db->affected_rows();
    $ids = explode(',', $ids);
    foreach($ids as $contact_id) {
      $this->update($contact_id);
    }
    return $rows;
  }

  /**
  * Check for existing groups with the same name
  *
  * @param string Name to check
  * @return string A group name which is unique for the current use
  */
  private function unique_groupname($name){
    $checkname = $name;
    $num = 2;
    $hit = false;
    do{
      $sql_result = $this->db->query(
        "SELECT 1 FROM ".get_table_name($this->db_groups).
        " WHERE del<>1".
            " AND user_id=?".
            " AND addressbook=?".
            " AND name=?",
        $this->user_id,
        $this->carddav_server_id,
        $checkname);

      // append number to make name unique
      if($hit = $this->db->num_rows($sql_result)){
        $checkname = $name . ' ' . $num++;
      }
    }
    while($hit > 0);

    return $checkname;
  }
  
  /*************************************************************************************************
   *                                                                                               *
   * CardDAV specific methods                                                                      *
   *                                                                                               *
   *************************************************************************************************/

  function carddav_addressbook_getctag($server){
    $this->write_log('Getting CardDAV-Addressbook ctag');
    $carddav_backend = new carddav_backend($server['url']);
    if($this->rc->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
    $carddav_backend->set_auth($server['username'], $this->rc->decrypt($server['password']));
    return $carddav_backend->get_ctag(false);
  }
  /**
   * Synchronize CardDAV-Addressbook
   *
   * @param  array   $server             CardDAV server array
   * @param  integer $carddav_contact_id CardDAV contact id
   * @param  string  $vcard_id           vCard id
   * @return boolean                     if no error occurred "true" else "false"
   */
  function carddav_addressbook_sync($server, $carddav_contact_id = null, $vcard_id = null, $start = 0, $timeout = 29){
    $any_data_synced = false;
    $this->write_log('Starting CardDAV-Addressbook synchronization');
    $carddav_backend = new carddav_backend($server['url']);
    if($this->rc->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
    $carddav_backend->set_auth($server['username'], $this->rc->decrypt($server['password']));
    if($carddav_backend->check_connection()){
      $this->write_log('Connected to the CardDAV-Server ' . $server['url']);
      if($vcard_id !== null){
        // server does not support REPORT request
        $noreport = $this->rc->config->get('carddav_noreport', array());
        if($noreport[$server['url']]){
          $elements = $carddav_backend->get_xml_vcard($vcard_id);
        }
        else{
          $elements = false;
        }
        try{
          $xml = new SimpleXMLElement($elements);
          if(count($xml->element) < 1){
            $elements = false;
          }
        }
        catch (Exception $e){
          $elements = false;
        }
        if($elements === false){
          $elements = $carddav_backend->get(false);
          $this->filter = null;
          $carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts();
          if(!isset($noreport[$server['url']])){
            $a_prefs['carddav_noreport'][$server['url']] = 1;
            $this->rc->user->save_prefs($a_prefs);
          }
        }
        else if($carddav_contact_id){
          $carddav_addressbook_contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
          $carddav_addressbook_contacts = array(
            $carddav_addressbook_contact['vcard_id'] => $carddav_addressbook_contact
          );
        }
      }
      else{
        $elements = $carddav_backend->get(false);
        $carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts();
      }
      try{
        $xml = new SimpleXMLElement($elements);
        if(!empty($xml->element)){
          foreach($xml->element as $element){
            if($start != 0 && time() - $start >= $timeout){
              return null;
            }
            $element_id = (string) $element->id;
            $element_etag = (string) $element->etag;
            $element_last_modified = (string) $element->last_modified;
            if(isset($carddav_addressbook_contacts[$element_id])){
              if($carddav_addressbook_contacts[$element_id]['etag'] != $element_etag ||
                $carddav_addressbook_contacts[$element_id]['last_modified'] != $element_last_modified
              ){
                $carddav_content = array(
                  'vcard' => $carddav_backend->get_vcard($element_id),
                  'vcard_id' => $element_id,
                  'etag' => $element_etag,
                  'last_modified' => $element_last_modified
                );
                if($this->carddav_addressbook_update($carddav_content)){
                  $any_data_synced = true;
                }
              }
            }
            else{
              $carddav_content = array(
                'vcard' => $carddav_backend->get_vcard($element_id),
                'vcard_id' => $element_id,
                'etag' => $element_etag,
                'last_modified' => $element_last_modified
              );
              if(!empty($carddav_content['vcard'])){
                if($this->carddav_addressbook_add($carddav_content)){
                  $any_data_synced = true;
                }
              }
            }
            unset($carddav_addressbook_contacts[$element_id]);
          }
        }
        else{
          $logging_message = 'No CardDAV XML-Element found!';
          if($carddav_contact_id !== null && $vcard_id !== null){
            $this->write_log($logging_message . ' The CardDAV-Server does not have a contact with the vCard id ' . $vcard_id);
          }
          else{
            $this->write_log($logging_message . ' The CardDAV-Server seems to have no contacts');
          }
        }

        if(!empty($carddav_addressbook_contacts)){
          foreach($carddav_addressbook_contacts as $vcard_id => $etag){
            if($this->carddav_addressbook_delete($vcard_id)){
              $any_data_synced = true;
            }
          }
        }

        if($any_data_synced === false){
          $this->write_log('all CardDAV-Data are synchronous, nothing todo!');
        }

        $this->write_log('Syncronization complete!');
      }
      catch (Exception $e){
        $this->write_log('CardDAV-Server XML-Response is malformed. Synchronization aborted!');
        return false;
      }
    }
    else{
      $this->write_log('Couldn\'t connect to the CardDAV-Server ' . $server['url']);
      return false;
    }

    return true;
  }

  /**
   * Adds a vCard to the CardDAV addressbook
   *
   * @param  array   $carddav_content CardDAV contents (vCard id, etag, last modified, etc.)
   * @return boolean
   */
  private function carddav_addressbook_add($carddav_content){
    $query = "SELECT * FROM " . get_table_name($this->db_name) .
             " WHERE carddav_server_id=? AND user_id=? and vcard_id=?";
    
    $result = $this->rc->db->limitquery(
      $query,
      0, 1,
      $this->carddav_server_id,
      $this->rc->user->data['user_id'],
      $carddav_content['vcard_id']
    );
    
    $exists = $this->rc->db->fetch_assoc($result);
    
    if(is_array($exists)){
      return $this->carddav_addressbook_update($carddav_content);
    }
    else{
      $vcard = new rcube_vcard();
      $vcard->load($carddav_content['vcard']);
      $save_data = $vcard->get_assoc();

      if(!isset($save_data['uid'])){
        $vcard->set('UID', $carddav_content['vcard_id'], false);
      }
      $save_data['vcard'] = $vcard->export(false);
      $save_data['vcard'] = $vcard->cleanup($save_data['vcard']);
      $database_column_contents = $this->convert_save_data($save_data);

      $query = "INSERT INTO " . get_table_name($this->db_name) . 
               " (carddav_server_id, user_id, etag, last_modified, vcard_id, vcard, words, firstname, surname, name, email)" .
               " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $this->rc->db->set_option('ignore_key_errors', true);
      $result = $this->rc->db->query(
        $query,
        $this->carddav_server_id,
        $this->rc->user->data['user_id'],
        $carddav_content['etag'],
        $carddav_content['last_modified'],
        $carddav_content['vcard_id'],
        $save_data['vcard'],
        $database_column_contents['words'],
        $database_column_contents['firstname'],
        $database_column_contents['surname'],
        $database_column_contents['name'],
        $database_column_contents['email']
      );
      if($this->rc->db->affected_rows($result)){
        $this->write_log('Added CardDAV-Contact to the local database with the vCard id ' . $carddav_content['vcard_id']);
        if($this->rc->action == 'refresh' || $this->rc->action == 'plugin.carddav-server-save'){
          $this->groups_downstream_sync($carddav_content['vcard_id'], $save_data);
        }
        return true;
      }
      else{
        $this->write_log('Couldn\'t add CardDAV-Contact to the local database with the vCard id ' . $carddav_content['vcard_id']);
        return false;
      }
    }
  }

  /**
   * Updates a vCard in the CardDAV-Addressbook
   *
   * @param  array   $carddav_content CardDAV contents (vCard id, etag, last modified, etc.)
   * @return boolean
   */
  private function carddav_addressbook_update($carddav_content){
    $vcard = new rcube_vcard();
    $vcard->extend_fieldmap(array('uid' => 'UID'));
    $vcard->load($carddav_content['vcard']);
    $save_data = $vcard->get_assoc();

    if(!isset($save_data['uid'])){
      $vcard->set('UID', $carddav_content['vcard_id'], false);
    }
    
    $save_data['vcard'] = $vcard->export(false);
    $save_data['vcard'] = $vcard->cleanup($save_data['vcard']);
    
    $database_column_contents = $this->convert_save_data($save_data);
    

    $query = "UPDATE " . get_table_name($this->db_name) . 
             " SET etag=?, last_modified=?, vcard=?, words=?, firstname=?, surname=?, name=?, email=?" .
             " WHERE vcard_id=? AND carddav_server_id=? AND user_id=?";

    $result = $this->rc->db->query(
      $query,
      $carddav_content['etag'],
      $carddav_content['last_modified'],
      $database_column_contents['vcard'],
      $database_column_contents['words'],
      $database_column_contents['firstname'],
      $database_column_contents['surname'],
      $database_column_contents['name'],
      $database_column_contents['email'],
      $carddav_content['vcard_id'],
      $this->carddav_server_id,
      $this->rc->user->ID
    );

    if($this->rc->db->affected_rows($result)){
      $this->write_log('CardDAV-Contact updated in the local database with the vCard id ' . $carddav_content['vcard_id']);
      if($this->rc->action == 'refresh' || $this->rc->action == 'plugin.carddav-server-save'){
        $this->groups_downstream_sync($carddav_content['vcard_id'], $save_data);
      }
      return true;
    }
    else{
      $this->write_log('Couldn\'t update CardDAV-Contact in the local database with the vCard id ' . $carddav_content['vcard_id']);
      return false;
    }
  }

  /**
   * Sync groups downstream
   *
   * @param  string $contact_uid
   * @param  array  $save_data
   * @return void
   */
  private function groups_downstream_sync($contact_uid, $save_data){
    $sql = 'DELETE FROM ' . get_table_name($this->db_groups) . ' WHERE del <> ?';
    $this->rc->db->query($sql, 0);
    $sql = 'SELECT * FROM ' . get_table_name($this->db_name) . ' WHERE user_id=? AND vcard_id=?';
    $res = $this->rc->db->query($sql, $this->rc->user->ID, $contact_uid);
    $res = $this->rc->db->fetch_assoc($res);
    $carddav_contact_id = $res['carddav_contact_id'];
    $sql = 'DELETE FROM ' . get_table_name($this->db_groupmembers) . ' WHERE contact_id=?';
    $this->rc->db->query($sql, $carddav_contact_id);
    if(isset($save_data['groups']) && is_array($save_data['groups'])){
      $categories = explode(',', $save_data['groups'][0]);
      foreach($categories as $category){
        $sql = 'SELECT * FROM ' . get_table_name($this->db_groups) . ' WHERE user_id=? AND name=?';
        $res = $this->rc->db->query($sql, $this->rc->user->ID, $category);
        $exists = $this->rc->db->fetch_assoc($res);
        if(!is_array($exists)){
          $sql = 'INSERT INTO ' . get_table_name($this->db_groups) . ' (user_id, changed, del, name, addressbook) VALUES (?, ?, ?, ?, ?)';
          $this->rc->db->query($sql, $this->rc->user->ID, date('Y-m-d H:i:s'), 0, $category, $this->carddav_server_id);
          $contactgroup_id = $this->rc->db->insert_id(get_table_name($this->db_groups));
        }
        else{
          $contactgroup_id = $exists['contactgroup_id'];
          $save_data['groups'][0] = str_replace($category, '', $save_data['groups'][0]);
          $save_data['groups'][0] = str_replace(',,', '', $save_data['groups'][0]);
          if(substr($save_data['groups'][0], strlen($save_data['groups'][0]) - 1, 1) == ','){
            $save_data['groups'][0] = substr($save_data['groups'][0], 0, strlen($save_data['groups'][0]) - 1);
          }
        }
        $sql = 'INSERT INTO ' . get_table_name($this->db_groupmembers) . ' (contactgroup_id, contact_id, created) VALUES (?, ?, ?)';
        $this->rc->db->query($sql, $contactgroup_id, $carddav_contact_id, date('Y-m-d H:i:s'));
      }
    }
  }

  /**
   * Deletes a vCard from the CardDAV addressbook
   *
   * @param  string  $vcard_id vCard id
   * @return boolean
   */
  private function carddav_addressbook_delete($vcard_id){
    if(!$vcard_id){
      return true;
    }
    
    $query = "DELETE FROM " . get_table_name($this->db_name) .
             " WHERE vcard_id=? AND carddav_server_id=? AND user_id = ?";

    $result = $this->rc->db->query($query, $vcard_id, $this->carddav_server_id, $this->rc->user->data['user_id']);

    if($this->rc->db->affected_rows($result)){
      $this->write_log('CardDAV-Contact deleted from the local database with the vCard id ' . $vcard_id);
      return true;
    }
    else{
      $this->write_log('Couldn\'t delete CardDAV-Contact from the local database with the vCard id ' . $vcard_id);
      return false;
    }
  }

  /**
   * Adds a Name field to vcard if missing
   *
   * @param  string $vcard vCard
   * @return string
   */
  private function vcard_check($raw){
    if($raw !== null){
      $vcard = new rcube_vcard();
      $vcard->load($raw);
      $data = $vcard->get_assoc();
      if(!isset($data['name'])){
        if(isset($data['surname'])){
          $data['name'] = $data['surname'];
        }
        else if(isset($data['displayname'])){
          $data['name'] = $data['displayname'];
        }
        else if(isset($data['nickname'])){
          $data['name'] = $data['nickname'];
        }
        else if(isset($data['firstname'])){
          $data['name'] = $data['firstname'];
        }
        else if(isset($data['middlename'])){
          $data['name'] = $data['middlename'];
        }
        else if(is_array($data['email:home'])){
          $data['name'] = $data['email:home'][0];
        }
        else if(is_array($data['email:work'])){
          $data['name'] = $data['email:work'][0];
        }
        else if(is_array($data['email:other'])){
          $data['name'] = $data['email:other'][0];
        }
        else{
          $data['name'] = 'unknown';
        }
        $vcard->set('surname', '', false);
        $vcard->set('firstname', current(explode('@', $data['name'])), false);
        $raw = $vcard->export();
      }
    }
    return $raw;
  }

  /**
   * Adds a CardDAV server contact
   *
   * @param  string  $vcard vCard
   * @return boolean
   */
  private function carddav_add($vcard){
    $sync = true;
    if($this->rc->action == 'copy'){
      $this->counter ++;
      $cids = get_input_value('_cid', RCUBE_INPUT_POST);
      $cids = explode(',', $cids);
      if($this->counter < count($cids)){
        $sync = false;
      }
    }
    $vcard = $this->vcard_check($vcard);
    $server = current(carddav::get_carddav_server($this->carddav_server_id));
    $arr = parse_url($server['url']);
    $carddav_backend = new carddav_backend($server['url']);
    if($this->rc->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
    $carddav_backend->set_auth($server['username'], $this->rc->decrypt($server['password']));
    if($carddav_backend->check_connection()){
      $vcard_id = $carddav_backend->add($vcard);
      if($sync && $this->rc->action != 'import'){
        if($this->rc->action == 'copy'){
          $vcard_id = false;
        }
        $this->carddav_addressbook_sync($server, false, $vcard_id);
        $cid = $this->rc->db->insert_id(get_table_name($this->db_name));
        return $cid;
      }
      else{
        return true;
      }
    }
    return false;
  }

  /**
   * Updates a CardDAV server contact
   *
   * @param  integer   $carddav_contact_id CardDAV contact id
   * @param  string    $vcard              The new vCard
   * @return boolean
   */
  private function carddav_update($carddav_contact_id, $vcard){
    $vcard = $this->vcard_check($vcard);
    $contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
    $server = current(carddav::get_carddav_server($this->carddav_server_id));
    $arr = parse_url($server['url']);
    $carddav_slow = $this->rc->config->get('carddav_slow_backends', array());
    if(isset($carddav_slow[strtolower($arr['host'])])){
      $carddav_backend = new carddav_backend($server['url'], '', (int) $carddav_slow[strtolower($arr['host'])], false);
    }
    else{
      $carddav_backend = new carddav_backend($server['url']);
    }
    if($this->rc->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
    $carddav_backend->set_auth($server['username'], $this->rc->decrypt($server['password']));

    if($carddav_backend->check_connection()){
      $carddav_backend->update($vcard, $contact['vcard_id']);
      $this->carddav_addressbook_sync($server, $carddav_contact_id, $contact['vcard_id']);

      return true;
    }

    return false;
  }

  /**
   * Deletes the CardDAV server contact
   *
   * @param  array $carddav_contact_ids CardDAV contact ids
   * @return mixed                      affected CardDAV contacts or false
   */
  private function carddav_delete($carddav_contact_ids){
    $server = current(carddav::get_carddav_server($this->carddav_server_id));
    $carddav_backend = new carddav_backend($server['url']);
    if($this->rc->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
    $carddav_backend->set_auth($server['username'], $this->rc->decrypt($server['password']));

    if($carddav_backend->check_connection()){
      foreach($carddav_contact_ids as $carddav_contact_id){
        $contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
        $carddav_backend->delete($contact['vcard_id']);
        $this->counter ++;
        if($this->counter < count($carddav_contact_ids)){
          continue;
        }
        if($this->counter > 1){
          $contact['vcard_id'] = false;
        }
        $this->carddav_addressbook_sync($server, $carddav_contact_id, $contact['vcard_id']);
      }

      return count($carddav_contact_ids);
    }

    return false;
  }
  
  /**
   * Get all CardDAV adressbook contacts
   *
   * @param  array $limit Limits (limit, offset)
   * @return array        CardDAV adressbook contacts
   */
  private function get_carddav_addressbook_contacts($limit = array()){
    $carddav_addressbook_contacts	= array();
    
    $order = $this->rc->config->get('addressbook_sort_col', 'name');
    switch($order){
      case 'surname':
        $sortby = 'surname, firstname, name, email';
        break;
      case 'firstname':
        $sortby = 'firstname, surname, name, email';
        break;
      default: // name is default
        $sortby = 'name, surname, firstname, email';
    }
    
    $search = $this->get_search_set();
    $query = "SELECT * FROM " . get_table_name($this->db_name) .
           " WHERE user_id=? AND carddav_server_id=? " . ($search ? ("AND " . $search) : "") . " ORDER BY " . $sortby . " ASC";
    
    if(empty($limit)){
      $result = $this->rc->db->query($query, $this->rc->user->data['user_id'], $this->carddav_server_id);
    }
    else{
      $result = $this->rc->db->limitquery($query, $limit['start'], $limit['length'], $this->rc->user->data['user_id'], $this->carddav_server_id);
    }
    while ($contact = $this->rc->db->fetch_assoc($result)){
      $carddav_addressbook_contacts[$contact['vcard_id']] = $contact;
    }
    return $carddav_addressbook_contacts;
  }

  /**
   * Get one CardDAV adressbook contact
   *
   * @param  integer $carddav_contact_id CardDAV contact id
   * @return array                       CardDAV adressbook contact
   */
  private function get_carddav_addressbook_contact($carddav_contact_id){
    $query = "SELECT * FROM " . get_table_name($this->db_name) .
             " WHERE user_id=? AND carddav_contact_id=?";

    $result = $this->rc->db->query($query, $this->rc->user->data['user_id'], $carddav_contact_id);

    return $this->rc->db->fetch_assoc($result);
  }

  /**
   * Sync groups upstream
   *
   * @param  integer $gid (group id)
   * @return void
   */
  function groups_upstream_sync($gid){
    $sql = 'SELECT * FROM ' . get_table_name($this->db_groupmembers) . ' WHERE contactgroup_id=?';
    $res = $this->db->query($sql, $gid);
    while($member = $this->db->fetch_assoc($res)){
      $members[] = $member;
    }
    if(is_array($members)){
      foreach($members as $member){
        $sql = 'SELECT * FROM ' . get_table_name($this->db_name) . ' WHERE user_id=? AND carddav_contact_id=?';
        $res = $this->db->query($sql, $this->rc->user->ID, $member['contact_id']);
        $contact = $this->db->fetch_assoc($res);
        $vcard = new rcube_vcard();
        $vcard->load($contact['vcard']);
        $save_data = $vcard->get_assoc();
        $save_data['groups'][0] = array();
        $sql = 'SELECT contactgroup_id FROM ' . get_table_name($this->db_groupmembers) . ' WHERE contact_id=?';
        $res = $this->db->query($sql, $member['contact_id']);
        while($group_id = $this->rc->db->fetch_assoc($res)){
          $group_ids[] = $group_id;
        }
        if(!empty($group_ids)){
          $categories = array();
          foreach($group_ids as $group_id){
            $sql = 'SELECT name FROM ' . get_table_name($this->db_groups) . ' WHERE contactgroup_id=? AND del=?';
            $res = $this->db->query($sql, $group_id['contactgroup_id'], 0);
            if($category = $this->rc->db->fetch_assoc($res)){
              if($category['name']){
                $categories[$category['name']] = $category['name'];
              }
            }
          }
          if(!empty($categories)){
            $save_data['groups'] = implode(',', $categories);
          }
        }
        $this->update($member['contact_id'], $save_data);
      }
    }
  }

  /**
   * Extended write log with pre defined logfile name and add version before the message content
   *
   * @param  string $message Log message
   * @return void
   */
  function write_log($message){
    carddav::write_log(' carddav_server_id: ' . $this->carddav_server_id . ' | ' . $message);
  }
}
?>