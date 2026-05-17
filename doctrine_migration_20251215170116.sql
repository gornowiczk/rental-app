-- Doctrine Migration File Generated on 2025-12-15 17:01:16

-- Version DoctrineMigrations\Version20250216114651
ALTER TABLE car CHANGE owner_id owner_id INT NOT NULL;
CREATE UNIQUE INDEX UNIQ_773DE69DFCFF3785 ON car (plate_number);
-- Version DoctrineMigrations\Version20250216114651 update table metadata;
INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250216114651', '2025-12-15 17:01:16', 0);
