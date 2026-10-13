<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class CatalogBuilder extends Builder
{
    public function delete(): never
    {
        throw new LogicException('Archive an HR catalog record instead of deleting history.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('HR catalog history cannot be deleted.');
    }

    public function truncate(): never
    {
        throw new LogicException('HR catalog history cannot be truncated.');
    }
}
