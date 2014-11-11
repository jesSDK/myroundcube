/*
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @author Daniel Morlock <daniel.morlock@awesome-it.de>
 * @author Rosali <rosali@myroundcube.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2012, Kolab Systems AG <contact@kolabsys.com>
 * Copyright (C) 2013, Awesome IT GbR <info@awesome-it.de>
 * Copyright (C) 2014, MyRoundcube.com <dev-team@myroundcube.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY. Without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/* Database driver */

CREATE TABLE IF NOT EXISTS calendars (
  calendar_id serial NOT NULL,
  user_id integer NOT NULL DEFAULT 0
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  name varchar(255) NOT NULL,
  color varchar(8) NOT NULL,
  showalarms smallint NOT NULL DEFAULT 1,
  tasks smallint NOT NULL DEFAULT 0,
  subscribed smallint NOT NULL DEFAULT 1,
  PRIMARY KEY (calendar_id)
);
CREATE INDEX calendars_user_name_idx ON calendars (user_id, name);

CREATE TABLE tasklists (
  tasklist_id serial NOT NULL,
  user_id integer NOT NULL
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  name varchar(255) NOT NULL,
  color varchar(8) NOT NULL,
  showalarms smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (tasklist_id)
);
CREATE INDEX tasklists_user_idx ON tasklists (user_id);

CREATE TABLE IF NOT EXISTS vevent (
  event_id serial NOT NULL,
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
  del smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (event_id)
);
CREATE INDEX vevent_uid_idx ON vevent (uid);
CREATE INDEX vevent_recurrence_idx ON vevent (recurrence_id);
CREATE INDEX vevent_calendar_notify_idx ON vevent (calendar_id, notifyat);

CREATE TABLE vtodo (
  task_id serial NOT NULL,
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
  notify timestamp without time zone DEFAULT NULL,
  PRIMARY KEY (task_id)
);
CREATE INDEX vtodo_tasklist_del_date_idx ON vtodo (tasklist_id, del, date);
CREATE INDEX vtodo_uid_idx ON vtodo (uid);

CREATE TABLE IF NOT EXISTS vevent_attachments (
  attachment_id serial NOT NULL,
  event_id integer NOT NULL DEFAULT 0
    REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  filename varchar(255) NOT NULL DEFAULT '',
  mimetype varchar(255) NOT NULL DEFAULT '',
  size integer NOT NULL DEFAULT 0,
  data text NOT NULL DEFAULT '',
  PRIMARY KEY (attachment_id)
);

CREATE TABLE IF NOT EXISTS vtodo_attachments (
  attachment_id serial NOT NULL,
  task_id integer NOT NULL DEFAULT 0
    REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE,
  filename varchar(255) NOT NULL DEFAULT '',
  mimetype varchar(255) NOT NULL DEFAULT '',
  size integer NOT NULL DEFAULT 0,
  data text NOT NULL DEFAULT '',
  PRIMARY KEY (attachment_id)
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

/* Kolab driver */

CREATE TABLE IF NOT EXISTS kolab_alarms (
  event_id varchar(255) NOT NULL,
  user_id integer NOT NULL
    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  notifyat timestamp without time zone DEFAULT NULL,
  dismissed smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (event_id)
);

/* CalDAV driver */

CREATE OR REPLACE FUNCTION update_last_change_column() RETURNS TRIGGER AS $$
BEGIN
   NEW.last_change = now();
   RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TABLE IF NOT EXISTS calendars_caldav_props (
  obj_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change timestamp without time zone NOT NULL DEFAULT now(),
  UNIQUE (obj_id, obj_type)
);
CREATE TRIGGER calendars_caldav_props_last_change
	BEFORE UPDATE ON calendars_caldav_props
	FOR EACH ROW EXECUTE PROCEDURE 
		update_last_change_column();

CREATE TABLE IF NOT EXISTS vevent_caldav_props (
  obj_id integer NOT NULL
    REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change timestamp without time zone NOT NULL DEFAULT now(),
  UNIQUE (obj_id, obj_type)
);
CREATE TRIGGER vevent_caldav_props_last_change
	BEFORE UPDATE ON vevent_caldav_props
	FOR EACH ROW EXECUTE PROCEDURE 
		update_last_change_column();

CREATE TABLE IF NOT EXISTS vtodo_caldav_props (
  obj_id integer NOT NULL
    REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change timestamp without time zone NOT NULL DEFAULT now(),
  UNIQUE (obj_id, obj_type)
);
CREATE TRIGGER vtodo_caldav_props_last_change
	BEFORE UPDATE ON vtodo_caldav_props
	FOR EACH ROW EXECUTE PROCEDURE 
		update_last_change_column();

/* iCal driver */

CREATE TABLE IF NOT EXISTS calendars_ical_props (
  obj_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change timestamp without time zone NOT NULL DEFAULT now(),
  UNIQUE (obj_id, obj_type)
);
CREATE TRIGGER calendars_ical_props_last_change
	BEFORE UPDATE ON calendars_ical_props
	FOR EACH ROW EXECUTE PROCEDURE 
		update_last_change_column();

CREATE TABLE IF NOT EXISTS vevent_ical_props (
  obj_id integer NOT NULL
    REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  "user" varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change timestamp without time zone NOT NULL DEFAULT now(),
  UNIQUE (obj_id, obj_type)
);
CREATE TRIGGER vevent_ical_props_last_change
	BEFORE UPDATE ON vevent_ical_props
	FOR EACH ROW EXECUTE PROCEDURE 
		update_last_change_column();

/* Google XML driver */

CREATE TABLE IF NOT EXISTS calendars_google_xml_props (
  obj_id integer NOT NULL
    REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE,
  url varchar(255) NOT NULL,
  last_change timestamp without time zone NOT NULL DEFAULT now(),
  UNIQUE (obj_id)
);
CREATE TRIGGER calendars_google_xml_props_last_change
	BEFORE UPDATE ON calendars_google_xml_props
	FOR EACH ROW EXECUTE PROCEDURE 
		update_last_change_column();

INSERT INTO system (name, value) VALUES ('myrc_calendar', 'initial');
