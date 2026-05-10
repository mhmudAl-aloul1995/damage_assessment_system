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
    'arcgis' => [
        'username' => env('ARCGIS_USERNAME'),
        'password' => env('ARCGIS_PASSWORD'),

        'buildings_url' => env(
            'ARCGIS_BUILDINGS_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0'
        ),

        'housing_units_url' => env(
            'ARCGIS_HOUSING_UNITS_URL',
            'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1'
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
            'status_field' => env('COMMITTEE_ARCGIS_STATUS_FIELD', 'field_status'),
            'status_value' => env('COMMITTEE_ARCGIS_STATUS_VALUE', 'Not_Completed'),
            'unit_target' => env('COMMITTEE_ARCGIS_UNIT_TARGET', 'unit'),
            'timeout' => env('COMMITTEE_ARCGIS_TIMEOUT', 90),
            'connect_timeout' => env('COMMITTEE_ARCGIS_CONNECT_TIMEOUT', 30),
            'retry_times' => env('COMMITTEE_ARCGIS_RETRY_TIMES', 2),
            'retry_sleep' => env('COMMITTEE_ARCGIS_RETRY_SLEEP', 1000),
            'verify_ssl' => env('COMMITTEE_ARCGIS_VERIFY_SSL', false),
        ],
    ],

];
