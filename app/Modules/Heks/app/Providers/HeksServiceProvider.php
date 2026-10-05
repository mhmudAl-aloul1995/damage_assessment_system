<?php

namespace App\Modules\Heks\Providers;

use App\Support\Modules\ModuleServiceProvider;

class HeksServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'heks';

    protected array $moduleCommands = [
        \App\Console\Commands\BackfillHeksKoboSubmissions::class,
        \App\Console\Commands\GenerateHeksKoboMappingReport::class,
        \App\Console\Commands\ImportHeksBoqCatalog::class,
        \App\Console\Commands\ImportHeksFollowUpBoqs::class,
        \App\Console\Commands\ImportHeksKoboFieldLabels::class,
        \App\Console\Commands\ImportHeksKoboFormMapping::class,
        \App\Console\Commands\LinkHeksEngineersToUsers::class,
        \App\Console\Commands\MergeHeksDuplicateFollowUps::class,
        \App\Console\Commands\SyncHeksKoboChoices::class,
        \App\Console\Commands\SyncHeksKoboSubmissions::class,
        \App\Console\Commands\VerifyHeksKoboChoices::class,
        \App\Console\Commands\VerifyHeksKoboFields::class,
    ];
}
