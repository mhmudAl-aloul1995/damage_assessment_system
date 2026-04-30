<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenerateArcgisMigration extends Command
{
    protected $signature = 'arcgis:generate-migration {table} {url} {--name=}';

    protected $description = 'Generate Laravel migration from ArcGIS schema with datatype';

    public function handle(): int
    {
        $table = $this->argument('table');
        $url = rtrim($this->argument('url'), '/');

        $token = $this->getToken();

        if (! $token) {
            $this->error('Failed to generate ArcGIS token');

            return self::FAILURE;
        }

        $response = Http::timeout(60)->get($url, [
            'f' => 'json',
            'token' => $token,
        ]);

        if (! $response->successful()) {
            $this->error($response->body());

            return self::FAILURE;
        }

        $json = $response->json();

        if (isset($json['error'])) {
            $this->error($json['error']['message'] ?? 'ArcGIS error');

            return self::FAILURE;
        }

        $fields = $json['fields'] ?? [];

        if (empty($fields)) {
            $this->error('No fields found');

            return self::FAILURE;
        }

        $migrationName = $this->option('name') ?: 'sync_arcgis_schema_for_'.$table.'_table';
        $timestamp = now()->format('Y_m_d_His');
        $file = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $columns = [];

        foreach ($fields as $field) {
            $column = $this->fieldToColumn($field, $table);

            if ($column) {
                $columns[] = $column;
            }
        }

        $columnsCode = implode("\n", $columns);

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('{$table}')) {
            Schema::create('{$table}', function (Blueprint \$table) {
                \$table->id();

{$columnsCode}

                if (!Schema::hasColumn('{$table}', 'raw_payload')) {
                    \$table->longText('raw_payload')->nullable();
                }

                \$table->timestamps();
            });
        } else {
            Schema::table('{$table}', function (Blueprint \$table) {
{$columnsCode}

                if (!Schema::hasColumn('{$table}', 'raw_payload')) {
                    \$table->longText('raw_payload')->nullable();
                }
            });
        }

        Schema::table('{$table}', function (Blueprint \$table) {
            try {
                if (Schema::hasColumn('{$table}', 'objectid')) {
                    \$table->index('objectid', 'idx_{$table}_objectid');
                }
            } catch (Throwable \$e) {}

            try {
                if (Schema::hasColumn('{$table}', 'globalid')) {
                    \$table->index('globalid', 'idx_{$table}_globalid');
                }
            } catch (Throwable \$e) {}

            try {
                if (Schema::hasColumn('{$table}', 'parentglobalid')) {
                    \$table->index('parentglobalid', 'idx_{$table}_parentglobalid');
                }
            } catch (Throwable \$e) {}
        });
    }

    public function down(): void
    {
        // Safe rollback: do not drop table automatically.
    }
};
PHP;

        file_put_contents($file, $content);

        $this->info('Migration created:');
        $this->line($file);

        return self::SUCCESS;
    }

    private function getToken(): ?string
    {
        $response = Http::asForm()
            ->timeout(60)
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'f' => 'json',
                'username' => config('services.arcgis.username'),
                'password' => config('services.arcgis.password'),
                'client' => 'referer',
                'referer' => config('app.url'),
                'expiration' => 60,
            ]);

        return $response->json()['token'] ?? null;
    }

    private function fieldToColumn(array $field, string $table): ?string
    {
        $originalName = $field['name'] ?? null;

        if (! $originalName) {
            return null;
        }

        $safe = Str::snake(strtolower($originalName));

        if (in_array($safe, ['id', 'shape', 'shape__area', 'shape__length'], true)) {
            return null;
        }

        $type = $field['type'] ?? '';
        $length = (int) ($field['length'] ?? 255);
        $nullable = ($field['nullable'] ?? true) ? '->nullable()' : '';
        $domain = $field['domain'] ?? null;

        $isMultiValue = $this->isLikelyMultiValueField($safe, $domain);

        if ($isMultiValue) {
            return $this->safeAddColumn($table, $safe, "\$table->text('{$safe}'){$nullable};");
        }

        return match ($type) {
            'esriFieldTypeOID' => $this->safeAddColumn($table, $safe, "\$table->unsignedBigInteger('{$safe}')->nullable();"),

            'esriFieldTypeGlobalID', 'esriFieldTypeGUID' => $this->safeAddColumn($table, $safe, "\$table->string('{$safe}', 50){$nullable};"),

            'esriFieldTypeString' => $length > 255
                    ? $this->safeAddColumn($table, $safe, "\$table->text('{$safe}'){$nullable};")
                    : $this->safeAddColumn($table, $safe, "\$table->string('{$safe}', {$length}){$nullable};"),

            'esriFieldTypeSmallInteger' => $this->safeAddColumn($table, $safe, "\$table->smallInteger('{$safe}'){$nullable};"),

            'esriFieldTypeInteger' => $this->safeAddColumn($table, $safe, "\$table->integer('{$safe}'){$nullable};"),

            'esriFieldTypeSingle', 'esriFieldTypeDouble' => $this->safeAddColumn($table, $safe, "\$table->decimal('{$safe}', 15, 4){$nullable};"),

            'esriFieldTypeDate' => $this->safeAddColumn($table, $safe, "\$table->dateTime('{$safe}'){$nullable};"),

            default => $this->safeAddColumn($table, $safe, "\$table->longText('{$safe}'){$nullable};"),
        };
    }

    private function safeAddColumn(string $table, string $column, string $definition): string
    {
        return <<<PHP
                if (!Schema::hasColumn('{$table}', '{$column}')) {
                    {$definition}
                }
PHP;
    }

    private function isLikelyMultiValueField(string $column, mixed $domain): bool
    {
        if (str_contains($column, 'type')
            || str_contains($column, 'reason')
            || str_contains($column, 'scope')
            || str_contains($column, 'damage')
            || str_contains($column, 'fire_locations')
            || str_contains($column, 'select_document')
            || str_contains($column, 'road_type')
            || str_contains($column, 'sidewalk_damage_type')
            || str_contains($column, 'traffic_signs_type')
            || str_contains($column, 'pole_type')
            || str_contains($column, 'ground_floor_use')
            || str_contains($column, 'building_roof_type')
            || str_contains($column, 'benef_type')
        ) {
            return true;
        }

        return false;
    }
}
