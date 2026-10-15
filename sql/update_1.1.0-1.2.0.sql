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

-- Anti-spam IMAP (1.2.0)
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN fk_user integer;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_active smallint DEFAULT 0 NOT NULL;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_junk_folders text;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_tasks text;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_date_last_scan datetime;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_last_error text;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_auth_mode varchar(16) DEFAULT 'master' NOT NULL;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_login varchar(255);
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_password text;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_imap_host varchar(255);
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_imap_port integer;
ALTER TABLE llx_crmclientconnector_emailaccount ADD COLUMN antispam_imap_encryption varchar(8);
