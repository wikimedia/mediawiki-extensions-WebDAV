-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: db/webdav.static.tokens.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/webdav_static_tokens (
  wdst_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  wdst_user_id INTEGER NOT NULL, wdst_token BLOB NOT NULL,
  wdst_expire INTEGER NOT NULL
);
