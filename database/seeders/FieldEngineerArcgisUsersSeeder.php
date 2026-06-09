<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldEngineerArcgisUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{ID: string, AssignedTo: string, NameEnglish: string, NameArabic: string, Email: string, ContactNumber: string}>
     */
    private const RECORDS = [
        ['ID' => '404277543', 'AssignedTo' => 'Ahmed.Muhhana', 'NameEnglish' => 'Ahmed Mohammed Abed Alrahmen Muhhana', 'NameArabic' => ' أحمد محمد عبد الرحمن مهنا', 'Email' => 'ahmedmuhanna98@gmail.com', 'ContactNumber' => '594161818'],
        ['ID' => '976066191', 'AssignedTo' => 'Ahmed.Asqool', 'NameEnglish' => 'Ahmed Omar Abbas Asqool', 'NameArabic' => 'أحمد عمر عباس عسقول', 'Email' => 'aasqool81@gmail.com', 'ContactNumber' => '567237520'],
        ['ID' => '410109441', 'AssignedTo' => 'Ahmed.Abudaqqa', 'NameEnglish' => 'Ahmed Sh. Ali Abu Daqqa', 'NameArabic' => 'احمد شريف علي ابو دقة', 'Email' => 'ahmedabudaqqa7@gmail.com', 'ContactNumber' => '568236661'],
        ['ID' => '407364017', 'AssignedTo' => 'Amjed.Nada', 'NameEnglish' => 'Amjed M. I. Nada', 'NameArabic' => 'امجد محمود إبراهيم أبو ندي ', 'Email' => 'amjadabunada9@gmail.com', 'ContactNumber' => '594381872'],
        ['ID' => '404547499', 'AssignedTo' => 'Bilal.AbuGhalaa', 'NameEnglish' => 'Bilal A.M AbuGhalaa', 'NameArabic' => 'بلال اكرم محمد ابو غالى ', 'Email' => 'ce.bilalgh@gmail.com', 'ContactNumber' => '594831500'],
        ['ID' => '932079502', 'AssignedTo' => 'Emad.Shahada', 'NameEnglish' => 'Emad M.M Shahada', 'NameArabic' => 'عماد محمود محمد شحادة', 'Email' => 'emad_shehada@hotmail.com', 'ContactNumber' => '599853112'],
        ['ID' => '403699762', 'AssignedTo' => 'Hamed.Alnajjar', 'NameEnglish' => 'Hamed M.A. Alnajjar', 'NameArabic' => 'حامد محمود عبد حويطي النجار', 'Email' => 'hamednjr63@gmail.com', 'ContactNumber' => '592386896'],
        ['ID' => '900148545', 'AssignedTo' => 'Hasan.Almasri', 'NameEnglish' => 'Hassan K. A. Al-Massry', 'NameArabic' => 'حسن  كمال عامر المصري', 'Email' => 'hasankamm6@gmail.com', 'ContactNumber' => '599384814'],
        ['ID' => '800889057', 'AssignedTo' => 'Hatim.Matar', 'NameEnglish' => 'Hatim Mohammad Hamdan Matar', 'NameArabic' => 'حاتم محمد حمدان مطر', 'Email' => 'hmattar1970@hotmaim.com', 'ContactNumber' => '599867747'],
        ['ID' => '400662938', 'AssignedTo' => 'Hessin.Naeim', 'NameEnglish' => 'Hesssin J. E. Naeim', 'NameArabic' => 'حسين جلال ابراهيم نعيم ', 'Email' => 'architect.husainnaim@gmail.com', 'ContactNumber' => '592395180'],
        ['ID' => '406966812', 'AssignedTo' => 'Ibrahim.Alhallaq', 'NameEnglish' => 'Ibrahim Kh. Ab. Al-Haalq', 'NameArabic' => 'ابراهيم خالد عبدالرؤوف الحلاق ', 'Email' => 'ibh122000@gmail.com', 'ContactNumber' => '598025502'],
        ['ID' => '410529473', 'AssignedTo' => 'Ibrahim.Alqedra', 'NameEnglish' => 'Ibrahim shawqi alqedra', 'NameArabic' => 'إبراهيم شوقي محمد القدرة', 'Email' => 'qc.qedra@hotmail.com', 'ContactNumber' => '598800719'],
        ['ID' => '404149486', 'AssignedTo' => 'Ismail.Alshakh.Ahmed', 'NameEnglish' => 'ISMAIL H. I. ALSHAKH AHMED', 'NameArabic' => 'اسماعيل حازم اسماعيل الشيخ احمد', 'Email' => 'ismhazem1998@gmail.com', 'ContactNumber' => '597248757'],
        ['ID' => '802723411', 'AssignedTo' => 'Kholood.Othman', 'NameEnglish' => 'KHOLOOD S. A. OTHMAN', 'NameArabic' => 'خلود صادق العبد عثمان', 'Email' => 'kho.daw2022@gmail.com', 'ContactNumber' => '598779493'],
        ['ID' => '803819531', 'AssignedTo' => 'Merwad.Almassri', 'NameEnglish' => 'Merwad M. S. Al- massri', 'NameArabic' => 'مرواد محمد سليم المصري', 'Email' => 'Mamola455@gmail.com', 'ContactNumber' => '567016661'],
        ['ID' => '901672147', 'AssignedTo' => 'Mohamed.Ghaith', 'NameEnglish' => 'Mohamed M.A Ghaith', 'NameArabic' => 'محمد محمود احمد غيث', 'Email' => 'g.01200100@gmail.com', 'ContactNumber' => '592939812'],
        ['ID' => '456901503', 'AssignedTo' => 'Mohammed.Alhaj', 'NameEnglish' => 'Mohammed  Al Hajj', 'NameArabic' => 'محمد عبد العزيز الحاج', 'Email' => 'hamoodyalsoryhaj@gmail.com', 'ContactNumber' => '592640034'],
        ['ID' => '900238437', 'AssignedTo' => 'Mohammed.Hammad', 'NameEnglish' => 'Mohammed Hassan Mohammed Hammad', 'NameArabic' => 'محمد حسن محمد حماد', 'Email' => 'mohammdhammad74@gmail.com', 'ContactNumber' => '599884482'],
        ['ID' => '801043050', 'AssignedTo' => 'Mohanad.Muamar', 'NameEnglish' => 'Mohanad Suliman MUAMAR', 'NameArabic' => 'مهند سليمان حسين معمر', 'Email' => 'eng.mohanad85@hotmail.com', 'ContactNumber' => '592500760'],
        ['ID' => '800843039', 'AssignedTo' => 'Mohand.Alshaer', 'NameEnglish' => 'Mohand N. M. Al-Shaer', 'NameArabic' => 'مهند نواف مصطفى الشاعر', 'Email' => 'mohanad.alshaer84@gmail.com', 'ContactNumber' => '567251572'],
        ['ID' => '802720227', 'AssignedTo' => 'Mohemmed.Isleem', 'NameEnglish' => 'Mohemmed Saleem Hammouda isleem', 'NameArabic' => 'محمد سليم حمودة سليم ', 'Email' => 'moh9160250@gmial.com', 'ContactNumber' => '599160250'],
        ['ID' => '803663152', 'AssignedTo' => 'Mustafa.Baroud', 'NameEnglish' => 'Mustafa Ali Mustafa Baroud', 'NameArabic' => 'مصطفى علي مصطفى بارود', 'Email' => 'abu.emaad.1964@gmail.com', 'ContactNumber' => '599680521'],
        ['ID' => '801340795', 'AssignedTo' => 'Osama.Alharazeen', 'NameEnglish' => 'Osama A. S. Al- Harazeen', 'NameArabic' => 'أسامة العبد سالم الحرازين', 'Email' => 'osama.a.s.alharazeen@gmail.com', 'ContactNumber' => '599366253'],
        ['ID' => '401272398', 'AssignedTo' => 'Osama.Alazeez', 'NameEnglish' => 'Osama H M Abed Alazeez', 'NameArabic' => ' أسامة حسام مصطفى عبد العزيز ', 'Email' => 'os0597060008@gmail.com', 'ContactNumber' => '597060008'],
        ['ID' => '404171316', 'AssignedTo' => 'Sara.Abumostafa', 'NameEnglish' => 'Sarah Ziad Ahmed Abu Mustafa', 'NameArabic' => 'سارة زياد احمد ابو مصطفى', 'Email' => 'saraziadmostafa@gmail.com', 'ContactNumber' => '595778691'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $summary = $this->importRows(self::RECORDS);

        $this->command?->info(sprintf(
            'Field engineer ArcGIS seed completed: %d rows, %d updated, %d unchanged, %d skipped, %d missing users.',
            $summary['rows'],
            $summary['updated'],
            $summary['unchanged'],
            $summary['skipped'],
            $summary['missing_users'],
        ));

        if ($summary['email_conflicts'] > 0) {
            $this->command?->warn(sprintf('Skipped %d email updates because the email belongs to another user.', $summary['email_conflicts']));
        }

        if ($summary['id_no_conflicts'] > 0) {
            $this->command?->warn(sprintf('Skipped %d ID updates because the ID belongs to another user.', $summary['id_no_conflicts']));
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return array{rows: int, updated: int, unchanged: int, skipped: int, missing_users: int, email_conflicts: int, id_no_conflicts: int}
     */
    public function importRows(iterable $rows): array
    {
        $summary = [
            'rows' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'missing_users' => 0,
            'email_conflicts' => 0,
            'id_no_conflicts' => 0,
        ];

        foreach ($rows as $row) {
            $row = $this->normalizeRowKeys($row);

            if (! $this->hasAnyValue($row)) {
                continue;
            }

            $summary['rows']++;

            $idNo = $this->normalizeText($this->rowValue($row, ['ID']));
            $email = $this->normalizeEmail($this->rowValue($row, ['E-mail', 'Email']));

            if ($idNo === null && $email === null) {
                $summary['skipped']++;

                continue;
            }

            $user = $this->findUser($idNo, $email);

            if (! $user instanceof User) {
                $summary['missing_users']++;

                continue;
            }

            $updates = array_filter([
                'username_arcgis' => $this->normalizeText($this->rowValue($row, ['AssignedTo', 'Assigned To'])),
                'name_en' => $this->normalizeText($this->rowValue($row, ['Name English'])),
                'name' => $this->normalizeText($this->rowValue($row, ['Name Arabic'])),
                'phone' => $this->normalizePhone($this->rowValue($row, ['Contact Number'])),
            ], fn (?string $value): bool => $value !== null);

            if ($email !== null) {
                if ($this->belongsToAnotherUser('email', $email, $user)) {
                    $summary['email_conflicts']++;
                } else {
                    $updates['email'] = $email;
                }
            }

            if ($idNo !== null && $user->id_no !== $idNo) {
                if ($this->belongsToAnotherUser('id_no', $idNo, $user)) {
                    $summary['id_no_conflicts']++;
                } elseif (blank($user->id_no)) {
                    $updates['id_no'] = $idNo;
                }
            }

            $user->fill($updates);

            if (! $user->isDirty()) {
                $summary['unchanged']++;

                continue;
            }

            $user->save();
            $summary['updated']++;
        }

        return $summary;
    }

    private function findUser(?string $idNo, ?string $email): ?User
    {
        if ($idNo !== null) {
            $user = User::query()->where('id_no', $idNo)->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        if ($email === null) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function belongsToAnotherUser(string $column, string $value, User $user): bool
    {
        $query = User::query()
            ->where($column, $value)
            ->whereKeyNot($user->getKey());

        if ($column === 'email') {
            $query = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($value)])
                ->whereKeyNot($user->getKey());
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $headings
     */
    private function rowValue(array $row, array $headings): mixed
    {
        foreach ($headings as $heading) {
            $key = $this->normalizeHeading($heading);

            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeading($key)] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasAnyValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeText($value) !== null) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeading(mixed $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $value));
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        return $value === null ? null : strtolower($value);
    }

    private function normalizePhone(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00970') && strlen($digits) === 14) {
            return '0'.substr($digits, 5);
        }

        if (str_starts_with($digits, '970') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '5') && strlen($digits) === 9) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        $value = trim(str_replace("\u{00A0}", ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
