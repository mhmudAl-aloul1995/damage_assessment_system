<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Cache
 *
 * @property string $key
 * @property string $value
 * @property int $expiration
 */
class Cache extends Model
{
    protected $table = 'cache';

    /**
     * @var string
     */
    protected $connection = 'mysql';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        '`key`',
        'value',
        'expiration',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => 'string',
            'value' => 'string',
            'expiration' => 'integer',
        ];
    }
}
