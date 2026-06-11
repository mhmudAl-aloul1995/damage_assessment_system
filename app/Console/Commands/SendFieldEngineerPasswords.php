<?php

namespace App\Console\Commands;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class SendFieldEngineerPasswords extends Command
{
    protected $signature = 'users:send-field-engineer-passwords
        {--role=QC/QA Engineer : The exact field engineer role name}
        {--dry-run : List matching users without resetting passwords or sending email}
        {--force : Required to reset passwords and send email}
        {--limit= : Maximum number of matching users to process}';

    protected $description = 'Reset and send passwords to users whose only role is the field engineer role';

    public function handle(): int
    {
        $roleName = trim((string) $this->option('role'));
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->limitOption();

        if ($roleName === '') {
            $this->error('The role option cannot be empty.');

            return self::FAILURE;
        }

        if (! $dryRun && ! (bool) $this->option('force')) {
            $this->error('Use --force to reset passwords and send emails, or --dry-run to preview matching users.');

            return self::FAILURE;
        }

        $users = $this->fieldEngineerOnlyUsers($roleName)
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get();

        if ($users->isEmpty()) {
            $this->warn("No users found with only the [{$roleName}] role.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email'],
            $users->map(fn (User $user): array => [
                $user->id,
                $user->name,
                $user->email,
            ])->all(),
        );

        if ($dryRun) {
            $this->info("Dry run complete. {$users->count()} matching user(s) found.");

            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($users as $user) {
            $password = (string) random_int(100000, 999999);

            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            Mail::to($user->email)->send(
                new WelcomeUserMail($user->email, $password)
            );

            $sentCount++;
        }

        $this->info("Passwords reset and emailed to {$sentCount} field engineer user(s).");

        return self::SUCCESS;
    }

    private function fieldEngineerOnlyUsers(string $roleName): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', $roleName))
            ->has('roles', '=', 1)
            ->orderBy('id');
    }

    private function limitOption(): ?int
    {
        $limit = $this->option('limit');

        if ($limit === null || $limit === '') {
            return null;
        }

        return max(1, (int) $limit);
    }
}
