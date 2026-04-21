<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'image' => 'The :attribute must be an image.',
    'max' => [
        'string' => 'The :attribute may not be greater than :max characters.',
        'file' => 'The :attribute may not be greater than :max kilobytes.',
    ],
    'mimes' => 'The :attribute must be a file of type: :values.',
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
    'array' => 'The :attribute must be an array.',
    'min' => [
        'array' => 'The :attribute must have at least :min items.',
    ],
    'in' => 'The selected :attribute is invalid.',
    'string' => 'The :attribute must be a string.',
    'attributes' => [
        'name' => 'name',
        'name_en' => 'English name',
        'email' => 'email',
        'phone' => 'phone number',
        'address' => 'address',
        'avatar' => 'profile picture',
        'roles' => 'roles',
        'roles.*' => 'role',
        'permissions' => 'permissions',
        'permissions.*' => 'permission',
        'id_no' => 'ID number',
        'contract_type' => 'contract type',
        'region' => 'region',
        'password' => 'password',
    ],
];
