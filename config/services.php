<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'browsershot' => [
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    ],
    'damage_assessment_pdf' => [
        'engine' => env('DAMAGE_ASSESSMENT_PDF_ENGINE', 'mpdf'),
    ],
    'kobotoolbox' => [
        'token' => env('KOBOTOOLBOX_TOKEN'),
        'rest_service_token' => env('KOBO_REST_SERVICE_TOKEN'),
        'borrower_name_field' => env('KOBO_BORROWER_NAME_FIELD'),
        'borrower_field_map' => json_decode((string) env('KOBO_BORROWER_FIELD_MAP', '[]'), true) ?: [],
        'borrower_boq_field_map' => json_decode((string) env('KOBO_BORROWER_BOQ_FIELD_MAP', '[]'), true) ?: [],
        'borrower_boq_group' => env('KOBO_BORROWER_BOQ_GROUP', 'group_fj89d65'),
        'timeout' => env('KOBOTOOLBOX_TIMEOUT', 60),
    ],
    'arcgis' => [
        'username' => env('ARCGIS_USERNAME'),
        'password' => env('ARCGIS_PASSWORD'),
        'referer' => env('ARCGIS_REFERER', env('APP_URL')),

        'target_service' => env('ARCGIS_TARGET_SERVICE'),
        'target_buildings_layer' => env('ARCGIS_TARGET_BUILDINGS_LAYER', 0),
        'target_units_layer' => env('ARCGIS_TARGET_UNITS_LAYER', 1),

        'source_service' => env('ARCGIS_SOURCE_SERVICE'),
        'source_buildings_layer' => env('ARCGIS_SOURCE_BUILDINGS_LAYER', 0),
        'source_units_layer' => env('ARCGIS_SOURCE_UNITS_LAYER', 1),

        'buildings_url' => env(
            'ARCGIS_BUILDINGS_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0'
        ),

        'housing_units_url' => env(
            'ARCGIS_HOUSING_UNITS_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1'
        ),

        'governorates_boundaries_url' => env(
            'ARCGIS_GOVERNORATES_BOUNDARIES_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/Features_gdb/FeatureServer/1'
        ),

        'neighborhoods_boundaries_url' => env(
            'ARCGIS_NEIGHBORHOODS_BOUNDARIES_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/Features_gdb/FeatureServer/0'
        ),

        'public_building_survey_layer_url' => env(
            'ARCGIS_PUBLIC_BUILDING_SURVEY_LAYER_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer'
        ),

        'public_building_survey_units_layer_url' => env(
            'ARCGIS_PUBLIC_BUILDING_SURVEY_UNITS_LAYER_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer/1'
        ),

        'road_facility_survey_layer_url' => env(
            'ARCGIS_ROAD_FACILITY_SURVEY_LAYER_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer'
        ),

        'road_facility_survey_items_layer_url' => env(
            'ARCGIS_ROAD_FACILITY_SURVEY_ITEMS_LAYER_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/1'
        ),
    ],
    'committee_decisions' => [
        'arcgis' => [
            'base_url' => env('COMMITTEE_ARCGIS_BASE_URL', ''),
            'token' => env('COMMITTEE_ARCGIS_TOKEN', ''),
            'token_url' => env('COMMITTEE_ARCGIS_TOKEN_URL', 'https://www.arcgis.com/sharing/rest/generateToken'),
            'referer' => env('COMMITTEE_ARCGIS_REFERER', env('APP_URL')),
            'building_layer_id' => env('COMMITTEE_ARCGIS_BUILDING_LAYER_ID', 0),
            'housing_unit_layer_id' => env('COMMITTEE_ARCGIS_HOUSING_UNIT_LAYER_ID', 1),
            'identifier_field' => env('COMMITTEE_ARCGIS_IDENTIFIER_FIELD', 'objectid'),
            'status_field' => env('COMMITTEE_ARCGIS_STATUS_FIELD', 'Field_status'),
            'status_value' => env('COMMITTEE_ARCGIS_STATUS_VALUE', 'Not_Completed'),
            'unit_target' => env('COMMITTEE_ARCGIS_UNIT_TARGET', 'unit'),
            'timeout' => env('COMMITTEE_ARCGIS_TIMEOUT', 15),
            'connect_timeout' => env('COMMITTEE_ARCGIS_CONNECT_TIMEOUT', 5),
            'retry_times' => env('COMMITTEE_ARCGIS_RETRY_TIMES', 1),
            'retry_sleep' => env('COMMITTEE_ARCGIS_RETRY_SLEEP', 250),
            'verify_ssl' => env('COMMITTEE_ARCGIS_VERIFY_SSL', false),
        ],
    ],

];
