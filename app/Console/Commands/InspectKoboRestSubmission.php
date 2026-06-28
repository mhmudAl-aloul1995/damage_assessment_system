<?php

namespace App\Console\Commands;

use App\Models\KoboRestSubmission;
use Illuminate\Console\Command;

class InspectKoboRestSubmission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kobo:inspect-rest-submission {id? : Kobo rest submission id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List scalar fields from a stored Kobo REST submission';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $submission = filled($this->argument('id'))
            ? KoboRestSubmission::query()->find($this->argument('id'))
            : KoboRestSubmission::query()
                ->whereIn('sync_status', ['skipped', 'failed', 'pending'])
                ->latest('id')
                ->first();

        if (! $submission instanceof KoboRestSubmission) {
            $this->components->error('No Kobo REST submission found.');

            return self::FAILURE;
        }

        $rows = collect($this->flatten($submission->payload ?? []))
            ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'value' => mb_strimwidth((string) $value, 0, 80, '...'),
            ])
            ->values()
            ->all();

        $this->components->info("Kobo submission #{$submission->id} fields:");
        $this->table(['key', 'value'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'/'.$key;

            if (is_array($value) && ! array_is_list($value)) {
                $flat = array_replace($flat, $this->flatten($value, $fullKey));
            } else {
                $flat[$fullKey] = $value;
            }
        }

        return $flat;
    }
}
