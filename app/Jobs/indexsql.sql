CREATE INDEX idx_buildings_globalid ON buildings(globalid);
CREATE INDEX idx_buildings_objectid ON buildings(objectid);
CREATE INDEX idx_housing_parentglobalid ON housing_units(parentglobalid);
CREATE INDEX idx_housing_objectid ON housing_units(objectid);

CREATE TABLE `exports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `filters` longtext DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `progress` int(11) DEFAULT 0
)