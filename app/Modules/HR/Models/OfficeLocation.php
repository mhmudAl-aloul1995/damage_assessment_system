<?php

namespace App\Modules\HR\Models;

class OfficeLocation extends CatalogModel
{
    protected $table = 'hr_office_locations';

    protected $fillable = ['code', 'name', 'name_en', 'active', 'timezone', 'city'];
}
