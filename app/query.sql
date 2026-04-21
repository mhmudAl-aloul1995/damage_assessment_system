SELECT
    -- 🔥 FULL NAME
    TRIM(
        CONCAT_WS (
            ' ',
            h.q_9_3_1_first_name,
            h.q_9_3_2_second_name__father,
            h.q_9_3_3_third_name__grandfather,
            h.q_9_3_4_last_name
        )
    ) AS full_name,
    -- 🔹 Building data
    TRIM(b.neighborhood) AS neighborhood,
    -- 🔹 Unit basic info
    TRIM(h.housing_unit_type) AS housing_unit_type,
    TRIM(h.security_situation_unit) AS security_situation_unit,
    TRIM(h.security_unit_info) AS security_unit_info,
    TRIM(h.unit_owner) AS unit_owner,
    TRIM(h.mobile_number) AS mobile_number,
    TRIM(h.additional_mobile) AS additional_mobile,
    TRIM(h.unit_damage_status) AS unit_damage_status,
    -- 🔹 Page 8
    TRIM(h.floor_number) AS floor_number,
    TRIM(h.housing_unit_number) AS housing_unit_number,
    TRIM(h.unit_direction) AS unit_direction,
    TRIM(h.damaged_area_m2) AS damaged_area_m2,
    TRIM(h.infra_type2) AS infra_type2,
    TRIM(h.house_unit_ownership) AS house_unit_ownership,
    TRIM(h.other_ownership) AS other_ownership,
    TRIM(h.occupied) AS occupied,
    TRIM(h.number_of_rooms) AS number_of_rooms,
    -- 🔹 Identity
    TRIM(h.identity_type1) AS identity_type1,
    TRIM(h.id_number1) AS id_number1,
    TRIM(h.passport1) AS passport1,
    TRIM(h.other_id1) AS other_id1,
    -- 🔹 Names
    TRIM(h.q_9_3_1_first_name) AS first_name,
    TRIM(h.q_9_3_2_second_name__father) AS father_name,
    TRIM(h.q_9_3_3_third_name__grandfather) AS grandfather_name,
    TRIM(h.q_9_3_4_last_name) AS last_name,
    -- 🔹 Personal
    TRIM(h.sex) AS sex,
    TRIM(h.owner_job) AS owner_job,
    TRIM(h.other_job) AS other_job,
    TRIM(h.age) AS age,
    TRIM(h.marital_status) AS marital_status,
    TRIM(h.ownership_image) AS ownership_image,
    -- 🔹 Page 10
    TRIM(h.no_spouses) AS no_spouses,
    TRIM(h.spouse1) AS spouse1,
    TRIM(h.spouse1_id) AS spouse1_id,
    TRIM(h.spouse2) AS spouse2,
    TRIM(h.spouse2_id) AS spouse2_id,
    TRIM(h.spouse3) AS spouse3,
    TRIM(h.spouse3_id) AS spouse3_id,
    TRIM(h.spouse4) AS spouse4,
    TRIM(h.spouse4_id) AS spouse4_id,
    TRIM(h.are_there_people_with_disability) AS are_there_people_with_disability,
    TRIM(h.number_of_people_with_disability) AS number_of_people_with_disability,
    TRIM(h.handicapped_type) AS handicapped_type,
    TRIM(h.other_handicapped) AS other_handicapped,
    TRIM(h.is_refugee) AS is_refugee,
    TRIM(h.unrwa_registration_number) AS unrwa_registration_number,
    -- 🔹 Page 11
    TRIM(h.number_of_nuclear_families) AS number_of_nuclear_families,
    TRIM(h.mchildren_001) AS mchildren_001,
    TRIM(h.myoung) AS myoung,
    TRIM(h.melderly) AS melderly,
    TRIM(h.fchildren) AS fchildren,
    TRIM(h.fyoung_001) AS fyoung_001,
    TRIM(h.felderly) AS felderly,
    TRIM(h.pregnant) AS pregnant,
    TRIM(h.lactating) AS lactating,
    -- 🔹 Page 12
    TRIM(h.the_unit_resident) AS the_unit_resident,
    TRIM(h.current_address) AS current_address,
    TRIM(h.tenant_name) AS tenant_name,
    TRIM(h.furniture_ownership) AS furniture_ownership,
    TRIM(h.percentage_of_damaged_furniture) AS percentage_of_damaged_furniture,
    TRIM(h.current_residence) AS current_residence,
    TRIM(h.current_residence_other) AS current_residence_other,
    TRIM(h.shelter_name) AS shelter_name,
    TRIM(h.shelter_type) AS shelter_type,
    TRIM(h.shelter_type_other) AS shelter_type_other,
    TRIM(h.governorate) AS governorate,
    TRIM(h.locality) AS locality,
    TRIM(h.neighborhood) AS unit_neighborhood,
    TRIM(h.street) AS street,
    TRIM(h.closest_facility2) AS closest_facility2,
    -- 🔹 Page 13
    TRIM(h.identity_type2) AS identity_type2,
    TRIM(h.rentee_id_passport_number) AS rentee_id_passport_number,
    TRIM(h.rentee_resident_full_name) AS rentee_resident_full_name,
    TRIM(h.q_13_3_1_first_name) AS rentee_first_name,
    TRIM(h.q_13_3_2_second_name__father) AS rentee_father_name,
    TRIM(h.q_13_3_3_third_name__grandfather) AS rentee_grandfather_name,
    TRIM(h.q_13_3_4_last_name__family) AS rentee_last_name,
    TRIM(h.rentee_mobile_number) AS rentee_mobile_number,
    TRIM(h.work_type) AS work_type,
    TRIM(h.other_work) AS other_work
FROM
    housing_units h
    LEFT JOIN buildings b ON b.globalid = h.parentglobalid;