CREATE TABLE /*_*/webdav_tokens (
	wdt_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	wdt_user_id int NOT NULL,
	wdt_filename varchar(255) NOT NULL,
	wdt_token varchar(255) NOT NULL,
	wdt_valid tinyint NOT NULL,
	wdt_expire int NOT NULL
);
