-- Copyright (C) 2025 Administrateur SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Anti-spam IMAP: log of the emails handled (rescued from junk, not rescued, spoofing)
CREATE TABLE llx_crmclientconnector_antispam_log(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_emailaccount integer NOT NULL,
	date_log datetime NOT NULL,
	folder varchar(255),
	message_id varchar(255),
	from_email varchar(255),
	subject varchar(255),
	date_mail datetime,
	fk_soc integer,
	fk_socpeople integer,
	action varchar(16) NOT NULL,
	auth_result varchar(16),
	auth_detail varchar(255),
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
