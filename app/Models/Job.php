<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Job
 *
 * @property int $id
 * @property string $queue
 * @property string $payload
 * @property bool $attempts
 * @property int|null $reserved_at
 * @property int $available_at
 * @property int $created_at
 */
class Job extends Model
{
    protected $table = 'jobs';

    /**
     * @var string
     */
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'queue',
        'payload',
        'attempts',
        'reserved_at',
        'available_at',
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
            'id' => 'integer',
            'queue' => 'string',
            'payload' => 'string',
            'attempts' => 'boolean',
            'reserved_at' => 'integer',
            'available_at' => 'integer',
            'created_at' => 'integer',
        ];
    }
}
