ALTER TABLE assets
ADD COLUMN asset_unavailable_reason TEXT DEFAULT NULL AFTER asset_status;
