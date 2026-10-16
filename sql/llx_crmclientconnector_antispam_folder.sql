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

-- Anti-spam IMAP: last message read in each folder of each mailbox
CREATE TABLE llx_crmclientconnector_antispam_folder(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_emailaccount integer NOT NULL,
	folder varchar(255) NOT NULL,
	uidvalidity bigint,
	last_uid bigint DEFAULT 0 NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
