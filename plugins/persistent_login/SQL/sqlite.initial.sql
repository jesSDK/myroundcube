CREATE TABLE IF NOT EXISTS 'auth_tokens' (
  'token' varchar(128) NOT NULL,
  'expires' datetime NOT NULL,
  'user_id' int(10) NOT NULL,
  'user_name' varchar(128) NOT NULL,
  'user_pass' varchar(128) NOT NULL,
  CONSTRAINT 'auth_tokens_ibfk_1' FOREIGN KEY ('user_id') REFERENCES 'users'
    ('user_id') ON DELETE
    CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS 'system' (
  name varchar(64) NOT NULL PRIMARY KEY,
  value text NOT NULL
);

INSERT INTO system (name, value) VALUES ('myrc_persistent_login', 'initial');