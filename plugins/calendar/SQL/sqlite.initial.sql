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

/* Kolab driver */

CREATE TABLE IF NOT EXISTS kolab_alarms (
  event_id varchar(255) NOT NULL PRIMARY KEY,
  user_id integer NOT NULL,
  notifyat datetime DEFAULT NULL,
  dismissed smallint NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
);

/* CalDAV driver */

CREATE TABLE IF NOT EXISTS calendars_caldav_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT calendars_caldav_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TRIGGER calendars_caldav_props_last_change
	AFTER UPDATE ON calendars_caldav_props
	FOR EACH ROW
BEGIN
	UPDATE calendars_caldav_props SET last_change = CURRENT_TIMESTAMP
		WHERE obj_id = old.obj_id AND obj_type = old.obj_type;
END;

CREATE TABLE IF NOT EXISTS vevent_caldav_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT vevent_caldav_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TRIGGER vevent_caldav_props_last_change
	AFTER UPDATE ON vevent_caldav_props
	FOR EACH ROW
BEGIN
	UPDATE vevent_caldav_props SET last_change = CURRENT_TIMESTAMP
		WHERE obj_id = old.obj_id AND obj_type = old.obj_type;
END;

CREATE TABLE IF NOT EXISTS vtodo_caldav_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  tag varchar(255) DEFAULT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT vtodo_caldav_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES vtodo (task_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TRIGGER vtodo_caldav_props_last_change
	AFTER UPDATE ON vtodo_caldav_props
	FOR EACH ROW
BEGIN
	UPDATE vtodo_caldav_props SET last_change = CURRENT_TIMESTAMP
		WHERE obj_id = old.obj_id AND obj_type = old.obj_type;
END;

/* iCal driver */

CREATE TABLE IF NOT EXISTS calendars_ical_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT calendars_ical_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TRIGGER calendars_ical_props_last_change
	AFTER UPDATE ON calendars_ical_props
	FOR EACH ROW
BEGIN
	UPDATE calendars_ical_props SET last_change = CURRENT_TIMESTAMP
		WHERE obj_id = old.obj_id AND obj_type = old.obj_type;
END;

CREATE TABLE IF NOT EXISTS vevent_ical_props (
  obj_id integer NOT NULL,
  obj_type varchar(8) NOT NULL,
  url varchar(255) NOT NULL,
  user varchar(255) DEFAULT NULL,
  pass varchar(1024) DEFAULT NULL,
  last_change datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT vevent_ical_props_ukey UNIQUE (obj_id, obj_type),
  FOREIGN KEY (obj_id) REFERENCES vevent (event_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TRIGGER vevent_ical_props_last_change
	AFTER UPDATE ON vevent_ical_props
	FOR EACH ROW
BEGIN
	UPDATE vevent_ical_props SET last_change = CURRENT_TIMESTAMP
		WHERE obj_id = old.obj_id AND obj_type = old.obj_type;
END;

/* Google XML driver */

CREATE TABLE IF NOT EXISTS calendars_google_xml_props (
  obj_id integer NOT NULL,
  url varchar(255) NOT NULL,
  last_change datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT calendars_google_xml_props_ukey UNIQUE (obj_id),
  FOREIGN KEY (obj_id) REFERENCES calendars (calendar_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TRIGGER calendars_google_xml_props_last_change
	AFTER UPDATE ON calendars_google_xml_props
	FOR EACH ROW
BEGIN
	UPDATE calendars_google_xml_props SET last_change = CURRENT_TIMESTAMP
		WHERE obj_id = old.obj_id;
END;

INSERT INTO system (name, value) VALUES ('myrc_calendar', 'initial');
