ALTER TABLE calendars_caldav_props ADD sync INTEGER NOT NULL DEFAULT '1';
ALTER TABLE calendars_ical_props ADD sync INTEGER NOT NULL DEFAULT '1';
UPDATE system SET value = 'initial|20141113|20141122|20141123|20141125' WHERE name = 'myrc_calendar';
