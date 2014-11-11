CREATE SEQUENCE planner_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS planner (
    id integer DEFAULT nextval('planner_ids'::regclass) NOT NULL,
    user_id integer NOT NULL,
    "starred" smallint NOT NULL DEFAULT 0,
    "datetime" timestamp with time zone DEFAULT NULL,
    "created" timestamp with time zone DEFAULT NULL,
    "text" text NOT NULL,
    "done" smallint NOT NULL DEFAULT 0,
    "deleted" smallint NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS "system" (
  name varchar(64) NOT NULL PRIMARY KEY,
  value text
);

INSERT INTO "system" (name, value) VALUES ('myrc_planner', 'initial');
    
ALTER TABLE ONLY planner
  ADD CONSTRAINT planner_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;