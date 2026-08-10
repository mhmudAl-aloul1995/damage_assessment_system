<?php

namespace App\Console\Commands;

use App\Support\ArabicNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class RefreshAccessCivilRegistry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'access-civil-registry:refresh
        {--path= : Microsoft Access .mdb file path}
        {--chunk=1000 : Rows inserted per batch}
        {--limit= : Optional maximum rows to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Microsoft Access civil registry into a searchable local cache table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: app_path('sokan_2005.mdb'));
        $chunkSize = max(100, (int) $this->option('chunk'));

        if (! is_file($path)) {
            $this->error("Access file was not found: {$path}");

            return self::FAILURE;
        }

        $outputPath = storage_path('app/access-civil-registry-'.Str::uuid().'.tsv');
        $stagingTable = 'access_civil_registry_records_staging';

        try {
            $this->exportAccessTable($path, $outputPath, $this->option('limit') ? max(1, (int) $this->option('limit')) : null);
            $this->prepareStagingTable($stagingTable);

            $imported = $this->importTsv($outputPath, $stagingTable, $chunkSize);

            DB::transaction(function () use ($stagingTable): void {
                DB::table('access_civil_registry_records')->delete();

                if (DB::connection()->getDriverName() === 'mysql') {
                    DB::statement("
                        INSERT INTO access_civil_registry_records
                            (
                                id_card_no,
                                first_name,
                                father_name,
                                grand_name,
                                family_name,
                                full_name,
                                full_name_normalized,
                                mother_name,
                                neighborhood,
                                birth_date,
                                created_at,
                                updated_at
                            )
                        SELECT
                            id_card_no,
                            first_name,
                            father_name,
                            grand_name,
                            family_name,
                            full_name,
                            full_name_normalized,
                            mother_name,
                            neighborhood,
                            birth_date,
                            created_at,
                            updated_at
                        FROM {$stagingTable}
                    ");
                } else {
                    DB::table('access_civil_registry_records')->insert(
                        DB::table($stagingTable)
                            ->select([
                                'id_card_no',
                                'first_name',
                                'father_name',
                                'grand_name',
                                'family_name',
                                'full_name',
                                'full_name_normalized',
                                'mother_name',
                                'neighborhood',
                                'birth_date',
                                'created_at',
                                'updated_at',
                            ])
                            ->get()
                            ->map(fn ($row): array => (array) $row)
                            ->all()
                    );
                }
            });

            $this->info("Access civil registry refreshed. Imported {$imported} records.");

            return self::SUCCESS;
        } finally {
            Schema::dropIfExists($stagingTable);

            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    private function exportAccessTable(string $path, string $outputPath, ?int $limit): void
    {
        $powershell = $this->windowsPowerShell32BitPath();

        if ($powershell === null) {
            throw new RuntimeException('32-bit Windows PowerShell was not found. It is required for the installed 32-bit Access driver.');
        }

        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$path = '__ACCESS_PATH__'
$output = '__OUTPUT_PATH__'
$connection = New-Object -ComObject ADODB.Connection
$connection.Open('Provider=Microsoft.Jet.OLEDB.4.0;Data Source=' + $path)
$recordset = New-Object -ComObject ADODB.Recordset
$sql = 'SELECT __LIMIT_CLAUSE__[الهوية], [الاسم], [الاب], [الجد], [العائلة], [اسم الام], [الحي], [تاريخ الميلاد] FROM [Sgaza]'
$recordset.Open($sql, $connection)
$utf8 = New-Object System.Text.UTF8Encoding($false)
$writer = New-Object System.IO.StreamWriter($output, $false, $utf8)
try {
    while (-not $recordset.EOF) {
        $values = @()
        for ($i = 0; $i -lt $recordset.Fields.Count; $i++) {
            $value = [string]$recordset.Fields.Item($i).Value
            $value = $value.Replace("`t", ' ').Replace("`r", ' ').Replace("`n", ' ').Trim()
            $values += $value
        }
        $writer.WriteLine(($values -join "`t"))
        $recordset.MoveNext()
    }
} finally {
    $writer.Close()
    $recordset.Close()
    $connection.Close()
}
POWERSHELL;

        $script = str_replace(
            ['__ACCESS_PATH__', '__OUTPUT_PATH__', '__LIMIT_CLAUSE__'],
            [
                str_replace("'", "''", $path),
                str_replace("'", "''", $outputPath),
                $limit !== null ? 'TOP '.$limit.' ' : '',
            ],
            $script
        );

        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $process = new Process([$powershell, '-NoProfile', '-EncodedCommand', $encoded]);
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()));
        }
    }

    private function importTsv(string $outputPath, string $stagingTable, int $chunkSize): int
    {
        $file = new \SplFileObject($outputPath);
        $file->setFlags(\SplFileObject::DROP_NEW_LINE);

        $rows = [];
        $imported = 0;
        $now = now();

        while (! $file->eof()) {
            $line = trim((string) $file->fgets());

            if ($line === '') {
                continue;
            }

            $columns = array_pad(explode("\t", $line), 8, null);
            [$idCardNo, $firstName, $fatherName, $grandName, $familyName, $motherName, $neighborhood, $birthDate] = $columns;

            $idCardNo = trim((string) $idCardNo);

            if ($idCardNo === '') {
                continue;
            }

            $fullName = trim(implode(' ', array_filter([
                trim((string) $firstName),
                trim((string) $fatherName),
                trim((string) $grandName),
                trim((string) $familyName),
            ])));

            $rows[] = [
                'id_card_no' => $idCardNo,
                'first_name' => $this->nullableString($firstName),
                'father_name' => $this->nullableString($fatherName),
                'grand_name' => $this->nullableString($grandName),
                'family_name' => $this->nullableString($familyName),
                'full_name' => $fullName !== '' ? $fullName : null,
                'full_name_normalized' => ArabicNameNormalizer::normalize($fullName),
                'mother_name' => $this->nullableString($motherName),
                'neighborhood' => $this->nullableString($neighborhood),
                'birth_date' => $this->accessDate($birthDate),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= $chunkSize) {
                DB::table($stagingTable)->insertOrIgnore($rows);
                $imported += count($rows);
                $rows = [];
                $this->line("Imported {$imported} Access records...");
            }
        }

        if ($rows !== []) {
            DB::table($stagingTable)->insertOrIgnore($rows);
            $imported += count($rows);
        }

        return $imported;
    }

    private function prepareStagingTable(string $stagingTable): void
    {
        Schema::dropIfExists($stagingTable);

        Schema::create($stagingTable, function (Blueprint $table): void {
            $table->id();
            $table->string('id_card_no', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('grand_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('full_name_normalized')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('neighborhood')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();

            $table->unique('id_card_no', 'access_civil_registry_staging_id_card_unique');
        });
    }

    private function windowsPowerShell32BitPath(): ?string
    {
        $path = (string) ($_SERVER['WINDIR'] ?? getenv('WINDIR') ?: 'C:\\Windows');
        $powershell = $path.'\\SysWOW64\\WindowsPowerShell\\v1.0\\powershell.exe';

        return is_file($powershell) ? $powershell : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function accessDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
