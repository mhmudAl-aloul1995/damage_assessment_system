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
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
    'arcgis' => [
        'username' => env('ARCGIS_USERNAME'),
        'password' => env('ARCGIS_PASSWORD'),
        'public_building_survey_layer_url' => env('ARCGIS_PUBLIC_BUILDING_SURVEY_LAYER_URL', 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer'),
        'public_building_survey_referer' => env('ARCGIS_PUBLIC_BUILDING_SURVEY_REFERER', 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer/0'),
        'road_facility_survey_layer_url' => env('ARCGIS_ROAD_FACILITY_SURVEY_LAYER_URL', 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer'),
        'road_facility_survey_referer' => env('ARCGIS_ROAD_FACILITY_SURVEY_REFERER', 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/0'),
    ],
    'committee_decisions' => [
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],
        'arcgis' => [
            'base_url' => env('COMMITTEE_ARCGIS_BASE_URL', ''),
            'token' => env('COMMITTEE_ARCGIS_TOKEN', ''),
            'token_url' => env('COMMITTEE_ARCGIS_TOKEN_URL', 'https://www.arcgis.com/sharing/rest/generateToken'),
            'referer' => env('COMMITTEE_ARCGIS_REFERER', env('APP_URL')),
            'building_layer_id' => env('COMMITTEE_ARCGIS_BUILDING_LAYER_ID', 0),
            'housing_unit_layer_id' => env('COMMITTEE_ARCGIS_HOUSING_UNIT_LAYER_ID', 1),
            'identifier_field' => env('COMMITTEE_ARCGIS_IDENTIFIER_FIELD', 'objectid'),
            'status_field' => env('COMMITTEE_ARCGIS_STATUS_FIELD', 'field_status'),
            'status_value' => env('COMMITTEE_ARCGIS_STATUS_VALUE', 'not_completed'),
            'unit_target' => env('COMMITTEE_ARCGIS_UNIT_TARGET', 'unit'),
        ],
    ],

];
