CREATE TABLE IF NOT EXISTS auth_tokens (
  token varchar(128) NOT NULL,
  expires timestamp NOT NULL,
  user_id integer NOT NULL
	REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  user_name varchar(128) NOT NULL,
  user_pass varchar(128) NOT NULL
);

CREATE TABLE IF NOT EXISTS "system" (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);

INSERT INTO "system" (name, value) VALUES ('myrc_persistent_login', 'initial');

CREATE INDEX ix_auth_tokens_user_id ON auth_tokens (user_id);