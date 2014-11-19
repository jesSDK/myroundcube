ALTER TABLE calendars ADD freebusy SMALLINT NOT NULL DEFAULT '0';

UPDATE TABLE system SET value = 'initial|20141113' WHERE name = 'myrc_calendar';