CREATE TABLE email2calendaruser (
  id serial NOT NULL,
  user_id integer NOT NULL,
  identity_id integer NOT NULL,
  email varchar(255) NOT NULL,
  calendaruser varchar(255) NOT NULL,
  PRIMARY KEY (id)
);
CREATE INDEX email2calendaruser_user_id_idx ON email2calendaruser (user_id);
CREATE INDEX email2calendaruser_identity_id_idx ON email2calendaruser (identity_id);

UPDATE system SET value = 'initial|20141113|20141122|20141123|20141125|20141205|20141231|20150107|20150128|20150206|20150228|20150319|20150329|20150417' WHERE name = 'myrc_calendar';

ALTER TABLE email2calendaruser
  ADD CONSTRAINT email2calendaruser_user_id_fkey FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT email2calendaruser_identity_id_fkey FOREIGN KEY (identity_id) REFERENCES identities (identity_id) ON DELETE CASCADE ON UPDATE CASCADE;
