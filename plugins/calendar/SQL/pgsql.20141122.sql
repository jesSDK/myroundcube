-- Database driver

CREATE SEQUENCE calendars_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS calendars (
  calendar_id integer DEFAULT nextval('calendars_seq'::text) PRIMARY KEY,
  user_id integer NOT NULL DEFAULT 0
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  name varchar(255) NOT NULL,
  color varchar(8) NOT NULL,
  showalarms smallint NOT NULL DEFAULT 1,
  tasks smallint NOT NULL DEFAULT 0,
  subscribed smallint NOT NULL DEFAULT 1
);
CREATE INDEX calendars_user_name_idx ON calendars (user_id, name);

CREATE SEQUENCE tasklists_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE tasklists (
  tasklist_id integer DEFAULT nextval('tasklists_seq'::text) PRIMARY KEY,
  user_id integer NOT NULL
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  name varchar(255) NOT NULL,
  color varchar(8) NOT NULL,
  showalarms smallint NOT NULL DEFAULT 0
);
CREATE INDEX tasklists_user_idx ON tasklists (user_id);

CREATE SEQUENCE vevent_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS vevent (
  event_id integer DEFAULT nextval('vevent_seq'::text) PRIMARY KEY,
  calendar_id integer NOT NULL DEFAULT 0
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  recurrence_id integer NOT NULL DEFAULT 0,
  exception timestamp without time zone NULL,
  exdate timestamp without time zone NULL,
  uid varchar(255) NOT NULL DEFAULT '',
  created timestamp without time zone NOT NULL,
  changed timestamp without time zone NOT NULL,
  sequence integer NOT NULL DEFAULT 0,
  "start" timestamp without time zone NOT NULL,
  "end" timestamp without time zone NOT NULL,
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
  notifyat timestamp without time zone DEFAULT NULL,
  del smallint NOT NULL DEFAULT 0
);
CREATE INDEX vevent_uid_idx ON vevent (uid);
CREATE INDEX vevent_recurrence_idx ON vevent (recurrence_id);
CREATE INDEX vevent_calendar_notify_idx ON vevent (calendar_id, notifyat);

CREATE SEQUENCE vtodo_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE vtodo (
  task_id integer DEFAULT nextval('vtodo_seq'::text) PRIMARY KEY,
  recurrence_id integer NOT NULL DEFAULT 0,
  tasklist_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  parent_id integer DEFAULT NULL,
  uid varchar(255) NOT NULL,
  created timestamp without time zone NOT NULL,
  changed timestamp without time zone NOT NULL,
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
  exception timestamp without time zone DEFAULT NULL,
  exdate timestamp without time zone DEFAULT NULL,
  organizer varchar(255) DEFAULT NULL,
  attendees text,
  notify timestamp without time zone DEFAULT NULL
);
CREATE INDEX vtodo_tasklist_del_date_idx ON vtodo (tasklist_id, del, date);
CREATE INDEX vtodo_uid_idx ON vtodo (uid);

CREATE SEQUENCE vevent_attachments_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS vevent_attachments (
  attachment_id integer DEFAULT nextval('vevent_attachment_seq'::text) PRIMARY KEY,
  event_id integer NOT NULL DEFAULT 0
    REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  filename varchar(255) NOT NULL DEFAULT '',
  mimetype varchar(255) NOT NULL DEFAULT '',
  size integer NOT NULL DEFAULT 0,
  data text NOT NULL DEFAULT ''
);

CREATE SEQUENCE vtodo_attachments_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS vtodo_attachments (
  attachment_id integer DEFAULT nextval('vtodo_attachment_seq'::text) PRIMARY KEY,
  task_id integer NOT NULL DEFAULT 0
    REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE,
  filename varchar(255) NOT NULL DEFAULT '',
  mimetype varchar(255) NOT NULL DEFAULT '',
  size integer NOT NULL DEFAULT 0,
  data text NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS itipinvitations (
  token varchar(64) NOT NULL,
  event_uid varchar(255) NOT NULL,
  user_id integer NOT NULL DEFAULT 0
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  event text NOT NULL,
  expires timestamp without time zone DEFAULT NULL,
  cancelled smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (token)
);
CREATE INDEX itipinvitations_user_event_idx ON itipinvitations (user_id, event_uid);

-- Kolab driver

CREATE SEQUENCE kolab_alarms_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS kolab_alarms (
  event_id integer DEFAULT nextval('kolab_alarms_seq'::text) PRIMARY KEY,
  user_id integer NOT NULL
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  notifyat timestamp without time zone DEFAULT NULL,
  dismissed smallint NOT NULL DEFAULT 0
);

-- CalDAV driver

CREATE TABLE IF NOT EXISTS calendars_caldav_props (
  obj_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE (obj_id, obj_type)
);

CREATE TABLE IF NOT EXISTS vevent_caldav_props (
  obj_id integer NOT NULL
    REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE (obj_id, obj_type)
);

CREATE TABLE IF NOT EXISTS vtodo_caldav_props (
  obj_id integer NOT NULL
    REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE (obj_id, obj_type)
);
-- iCal driver

CREATE TABLE IF NOT EXISTS calendars_ical_props (
  obj_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE (obj_id, obj_type)
);

CREATE TABLE IF NOT EXISTS vevent_ical_props (
  obj_id integer NOT NULL
    REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE (obj_id, obj_type)
);

-- Google XML driver

CREATE TABLE IF NOT EXISTS calendars_google_xml_props (
  obj_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  url varchar(255) NOT NULL,
  last_change varchar(19) NOT NULL DEFAULT '1000-12-31 00:00:00',
  UNIQUE (obj_id)
);

DELETE FROM system WHERE name = 'myrc_calendar';

DELETE FROM plugin_manager WHERE conf = 'defaults_overwrite';

DELETE FROM db_config WHERE env = 'calendar';

INSERT INTO system (name, value) VALUES ('myrc_calendar', 'initial|20141113|20141122');
