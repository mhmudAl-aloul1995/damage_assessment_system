<?php

use App\Models\User;
use Database\Seeders\FieldEngineerArcgisUsersSeeder;

it('updates arcgis username and matching user columns by id number', function (): void {
    $user = User::factory()->create([
        'id_no' => '404277543',
        'name' => 'Old Arabic Name',
        'name_en' => null,
        'email' => 'old@example.com',
        'phone' => null,
        'username_arcgis' => null,
    ]);

    $summary = app(FieldEngineerArcgisUsersSeeder::class)->importRows([
        [
            'Name English' => 'Ahmed Mohammed Abed Alrahmen Muhhana',
            'AssignedTo' => 'Ahmed.Muhhana',
            'Name Arabic' => 'Ahmed Arabic Name',
            'ID' => '404277543',
            'E-mail' => 'AHMEDMUHANNA98@GMAIL.COM',
            'Contact Number' => '594161818',
        ],
    ]);

    $user->refresh();

    expect($summary)
        ->toMatchArray([
            'rows' => 1,
            'updated' => 1,
            'unchanged' => 0,
            'skipped' => 0,
            'missing_users' => 0,
        ])
        ->and($user->username_arcgis)->toBe('Ahmed.Muhhana')
        ->and($user->name_en)->toBe('Ahmed Mohammed Abed Alrahmen Muhhana')
        ->and($user->name)->toBe('Ahmed Arabic Name')
        ->and($user->email)->toBe('ahmedmuhanna98@gmail.com')
        ->and($user->phone)->toBe('0594161818');
});

it('falls back to email matching and fills a blank id number', function (): void {
    $user = User::factory()->create([
        'id_no' => null,
        'email' => 'aasqool81@gmail.com',
        'username_arcgis' => null,
    ]);

    $summary = app(FieldEngineerArcgisUsersSeeder::class)->importRows([
        [
            'AssignedTo' => 'Ahmed.Asqool',
            'ID' => '976066191',
            'E-mail' => 'aasqool81@gmail.com',
        ],
    ]);

    $user->refresh();

    expect($summary['updated'])->toBe(1)
        ->and($summary['missing_users'])->toBe(0)
        ->and($user->id_no)->toBe('976066191')
        ->and($user->username_arcgis)->toBe('Ahmed.Asqool');
});

it('does not overwrite email or id number that belong to another user', function (): void {
    $matchedUser = User::factory()->create([
        'id_no' => '410109441',
        'email' => 'matched@example.com',
        'username_arcgis' => null,
    ]);
    $emailOwner = User::factory()->create([
        'id_no' => '999999999',
        'email' => 'ahmedabudaqqa7@gmail.com',
    ]);

    $summary = app(FieldEngineerArcgisUsersSeeder::class)->importRows([
        [
            'AssignedTo' => 'Ahmed.Abudaqqa',
            'ID' => '410109441',
            'E-mail' => 'ahmedabudaqqa7@gmail.com',
        ],
        [
            'AssignedTo' => 'Conflict.Id',
            'ID' => '999999999',
            'E-mail' => 'matched@example.com',
        ],
    ]);

    $matchedUser->refresh();
    $emailOwner->refresh();

    expect($summary['updated'])->toBe(2)
        ->and($summary['email_conflicts'])->toBe(2)
        ->and($matchedUser->email)->toBe('matched@example.com')
        ->and($matchedUser->username_arcgis)->toBe('Ahmed.Abudaqqa')
        ->and($emailOwner->email)->toBe('ahmedabudaqqa7@gmail.com')
        ->and($emailOwner->username_arcgis)->toBe('Conflict.Id');
});
