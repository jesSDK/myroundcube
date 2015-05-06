-- Database driver

CREATE TABLE IF NOT EXISTS calendars (
  calendar_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL DEFAULT 0,
  name varchar(255) NOT NULL,
  color varchar(8) NOT NULL,
  showalarms smallint NOT NULL DEFAULT 1,
  tasks smallint NOT NULL DEFAULT 0,
  subscribed smallint NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX calendars_user_name_idx ON calendars (user_id, name);

CREATE TABLE tasklists (
  tasklist_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL,
  name varchar(255) NOT NULL,
  color varchar(8) NOT NULL,
  showalarms smallint NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX tasklists_user_idx ON tasklists (user_id);

CREATE TABLE IF NOT EXISTS vevent (
  event_id integer NOT NULL PRIMARY KEY,
  calendar_id integer NOT NULL DEFAULT 0,
  recurrence_id integer NOT NULL DEFAULT 0,
  exception datetime NULL,
  exdate datetime NULL,
  uid varchar(255) NOT NULL DEFAULT '',
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  changed datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  sequence integer NOT NULL DEFAULT 0,
  start datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  end datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  recurrence text DEFAULT NULL,
  title varchar(255) NOT NULL,
  description text NOT NULL,
  location varchar(255) NOT NULL DEFAULT '',
  categories varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  all_day smallint NOT NULL DEFAULT 0,
  free_busy smallint NOT NULL DEFAULT 0,
  priority smallint NOT NULL DEFAULT 0,
  sensitivity smallint NOT NULL DEFAULT 0,
  alarms varchar(255) DEFAULT NULL,
  attendees text DEFAULT NULL,
  notifyat datetime DEFAULT NULL,
  del smallint NOT NULL DEFAULT 0,
  FOREIGN KEY (calendar_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX vevent_uid_idx ON vevent (uid);
CREATE INDEX vevent_recurrence_idx ON vevent (recurrence_id);
CREATE INDEX vevent_calendar_notify_idx ON vevent (calendar_id, notifyat);

CREATE TABLE vtodo (
  task_id integer NOT NULL PRIMARY KEY,
  recurrence_id integer NOT NULL DEFAULT 0,
  tasklist_id integer NOT NULL,
  parent_id integer DEFAULT NULL,
  uid varchar(255) NOT NULL,
  created datetime NOT NULL,
  changed datetime NOT NULL,
  del smallint NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL,
  description text,
  tags text,
  date varchar(10) DEFAULT NULL,
  time varchar(5) DEFAULT NULL,
  startdate varchar(10) DEFAULT NULL,
  starttime varchar(5) DEFAULT NULL,
  flagged smallint NOT NULL DEFAULT 0,
  complete float NOT NULL DEFAULT 0,
  status varchar(16) NOT NULL DEFAULT '',
  alarms varchar(255) DEFAULT NULL,
  recurrence text DEFAULT NULL,
  exception datetime DEFAULT NULL,
  exdate datetime DEFAULT NULL,
  organizer varchar(255) DEFAULT NULL,
  attendees text,
  notify datetime DEFAULT NULL,
  FOREIGN KEY (tasklist_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX vtodo_tasklist_del_date_idx ON vtodo (tasklist_id, del, date);
CREATE INDEX vtodo_uid_idx ON vtodo (uid);

CREATE TABLE IF NOT EXISTS vevent_attachments (
  attachment_id integer NOT NULL PRIMARY KEY,
  event_id integer NOT NULL DEFAULT 0,
  filename varchar(255) NOT NULL DEFAULT '',
  mimetype varchar(255) NOT NULL DEFAULT '',
  size integer NOT NULL DEFAULT 0,
  data blob NOT NULL DEFAULT '',
  FOREIGN KEY (event_id) REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS vtodo_attachments (
  attachment_id integer NOT NULL PRIMARY KEY,
  task_id integer NOT NULL DEFAULT 0,
  filename varchar(255) NOT NULL DEFAULT '',
  mimetype varchar(255) NOT NULL DEFAULT '',
  size integer NOT NULL DEFAULT 0,
  data blob NOT NULL DEFAULT '',
  FOREIGN KEY (task_id) REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS itipinvitations (
  token varchar(64) NOT NULL PRIMARY KEY,
  event_uid varchar(255) NOT NULL,
  user_id integer NOT NULL DEFAULT 0,
  event text NOT NULL,
  expires datetime DEFAULT NULL,
  cancelled smallint NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX itipinvitations_user_event_idx ON itipinvitations (user_id, event_uid);

-- Kolab driver

CREATE TABLE IF NOT EXISTS kolab_alarms (
  event_id varchar(255) NOT NULL PRIMARY KEY,
  user_id integer NOT NULL,
  notifyat datetime DEFAULT NULL,
  dismissed smallint NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- CalDAV driver

CREATE TABLE IF NOT EXISTS calendars_caldav_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  CONSTRAINT calendars_caldav_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS vevent_caldav_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  CONSTRAINT vevent_caldav_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS vtodo_caldav_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  CONSTRAINT vtodo_caldav_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- iCal driver

CREATE TABLE IF NOT EXISTS calendars_ical_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  CONSTRAINT calendars_ical_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS vevent_ical_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  CONSTRAINT vevent_ical_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Google XML driver

CREATE TABLE IF NOT EXISTS calendars_google_xml_props (
  obj_id integer NOT NULL,
  url varchar(255) NOT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  CONSTRAINT calendars_google_xml_props_ukey UNIQUE (obj_id),
  FOREIGN KEY (obj_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);

DELETE FROM system WHERE name = 'myrc_calendar';

DELETE FROM plugin_manager WHERE conf = 'defaults_overwrite';

DELETE FROM db_config WHERE env = 'calendar';

INSERT INTO system (name, value) VALUES ('myrc_calendar', 'initial|20141113|20141122');
