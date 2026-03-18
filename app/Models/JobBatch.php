<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class JobBatch
 *
 * @property string $id
 * @property string $name
 * @property int $total_jobs
 * @property int $pending_jobs
 * @property int $failed_jobs
 * @property string $failed_job_ids
 * @property string|null $options
 * @property int|null $cancelled_at
 * @property int $created_at
 * @property int|null $finished_at
 */
class JobBatch extends Model
{
    protected $table = 'job_batches';

    /**
     * @var string
     */
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'total_jobs',
        'pending_jobs',
        'failed_jobs',
        'failed_job_ids',
        'options',
        'cancelled_at',
        'finished_at',
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
            'id' => 'string',
            'name' => 'string',
            'total_jobs' => 'integer',
            'pending_jobs' => 'integer',
            'failed_jobs' => 'integer',
            'failed_job_ids' => 'string',
            'options' => 'string',
            'cancelled_at' => 'integer',
            'created_at' => 'integer',
            'finished_at' => 'integer',
        ];
    }
}
