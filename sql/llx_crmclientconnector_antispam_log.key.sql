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

ALTER TABLE llx_crmclientconnector_antispam_log ADD INDEX idx_crmclientconnector_antispam_log_account (fk_emailaccount, date_log);
ALTER TABLE llx_crmclientconnector_antispam_log ADD INDEX idx_crmclientconnector_antispam_log_message (message_id);
ALTER TABLE llx_crmclientconnector_antispam_log ADD INDEX idx_crmclientconnector_antispam_log_date (date_log);
