START TRANSACTION;

/* edit_assessments */
DROP TABLE IF EXISTS merged_edit_assessments;

CREATE TABLE merged_edit_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    global_id TEXT NOT NULL,
    type ENUM('building_table','housing_table') NOT NULL,
    field_name VARCHAR(255) NOT NULL,
    field_value TEXT NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

INSERT INTO merged_edit_assessments (
    global_id, type, field_name, field_value, user_id, created_at, updated_at
)
SELECT
    global_id,
    type,
    field_name,
    field_value,
    user_id,
    created_at,
    MAX(updated_at) AS updated_at
FROM (
    SELECT global_id, type, field_name, field_value, user_id, created_at, updated_at
    FROM damage.edit_assessments

    UNION ALL

    SELECT global_id, type, field_name, field_value, user_id, created_at, updated_at
    FROM damage_assessment.edit_assessments
) x
GROUP BY global_id, type, field_name, field_value, user_id, created_at;


/* assigned_assessment_users */
DROP TABLE IF EXISTS merged_assigned_assessment_users;

CREATE TABLE merged_assigned_assessment_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    manager_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(255) NOT NULL,
    building_id INT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

INSERT INTO merged_assigned_assessment_users (
    manager_id, user_id, type, building_id, created_at, updated_at
)
SELECT
    manager_id,
    user_id,
    type,
    building_id,
    created_at,
    MAX(updated_at) AS updated_at
FROM (
    SELECT manager_id, user_id, type, building_id, created_at, updated_at
    FROM damage.assigned_assessment_users

    UNION ALL

    SELECT manager_id, user_id, type, building_id, created_at, updated_at
    FROM damage_assessment.assigned_assessment_users
) x
GROUP BY manager_id, user_id, type, building_id, created_at;


/* building_statuses - يعتمد على أحدث updated_at */
DROP TABLE IF EXISTS merged_building_statuses;

CREATE TABLE merged_building_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    type VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

INSERT INTO merged_building_statuses (
    building_id, status_id, user_id, type, notes, created_at, updated_at
)
SELECT
    building_id,
    status_id,
    user_id,
    type,
    notes,
    created_at,
    updated_at
FROM (
    SELECT
        x.*,
        ROW_NUMBER() OVER (
            PARTITION BY building_id, type, created_at
            ORDER BY updated_at DESC, id DESC
        ) AS rn
    FROM (
        SELECT id, building_id, status_id, user_id, type, notes, created_at, updated_at
        FROM damage.building_statuses

        UNION ALL

        SELECT id, building_id, status_id, user_id, type, notes, created_at, updated_at
        FROM damage_assessment.building_statuses
    ) x
) ranked
WHERE rn = 1;




/* housing_statuses - يعتمد على أحدث updated_at */
DROP TABLE IF EXISTS merged_housing_statuses;

CREATE TABLE merged_housing_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    housing_id INT NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    type VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

INSERT INTO merged_housing_statuses (
    housing_id, status_id, user_id, type, notes, created_at, updated_at
)
SELECT
    housing_id,
    status_id,
    user_id,
    type,
    notes,
    created_at,
    updated_at
FROM (
    SELECT
        x.*,
        ROW_NUMBER() OVER (
            PARTITION BY housing_id, type, created_at
            ORDER BY updated_at DESC, id DESC
        ) AS rn
    FROM (
        SELECT id, housing_id, status_id, user_id, type, notes, created_at, updated_at
        FROM damage.housing_statuses

        UNION ALL

        SELECT id, housing_id, status_id, user_id, type, notes, created_at, updated_at
        FROM damage_assessment.housing_statuses
    ) x
) ranked
WHERE rn = 1;

COMMIT;