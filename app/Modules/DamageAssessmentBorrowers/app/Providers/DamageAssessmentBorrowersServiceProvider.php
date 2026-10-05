<?php

namespace App\Modules\DamageAssessmentBorrowers\Providers;

use App\Support\Modules\ModuleServiceProvider;

class DamageAssessmentBorrowersServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'damage-assessment-borrowers';

    protected array $moduleCommands = [
        \App\Console\Commands\ImportBorrowerVisitEligibility::class,
        \App\Console\Commands\ImportDamageAssessmentBorrowers::class,
        \App\Console\Commands\ImportIqradNewCensus::class,
        \App\Console\Commands\MergeDamageAssessmentBorrowerDuplicates::class,
        \App\Console\Commands\SyncBorrowerFormNumbers::class,
        \App\Console\Commands\SyncIqradKoboBorrowers::class,
        \App\Console\Commands\UpdateBorrowerLoanNetAmounts::class,
    ];
}
