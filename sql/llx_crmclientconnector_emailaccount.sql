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


CREATE TABLE llx_crmclientconnector_emailaccount(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    email_account varchar(255) NOT NULL,
	label varchar(255), 
	fk_project integer, 
	description text, 
	note_public text, 
	note_private text, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	last_main_doc varchar(255), 
	import_key varchar(14), 
	model_pdf varchar(255), 
	status integer NOT NULL,
	fk_user integer,
	antispam_active smallint DEFAULT 0 NOT NULL,
	antispam_junk_folders text,
	antispam_tasks text,
	antispam_date_last_scan datetime,
	antispam_last_error text,
	antispam_auth_mode varchar(16) DEFAULT 'master' NOT NULL,
	antispam_login varchar(255),
	antispam_password text,
	antispam_imap_host varchar(255),
	antispam_imap_port integer,
	antispam_imap_encryption varchar(8)
) ENGINE=innodb;
