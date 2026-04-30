ALTER TABLE edit_assessments
ADD INDEX idx_edit_latest (type, global_id(191), field_name, id);

ALTER TABLE edit_assessments
ADD INDEX idx_edit_global (type, global_id(191), id);

ALTER TABLE edit_assessments
ADD INDEX idx_edit_status (type, field_name, global_id(191), id);