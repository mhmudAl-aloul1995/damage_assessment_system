<?php

use Illuminate\Support\Collection;

if (!function_exists('getFilterLabel')) {

    function getFilterLabel(Collection $filters, string $listName, $value): string
    {
        return optional(
            $filters->get($listName, collect())->firstWhere('name', $value)
        )->label ?? '-';
    }

}