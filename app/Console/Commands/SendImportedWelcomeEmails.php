<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;

class SendImportedWelcomeEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:send-welcome-emails
        {--instructor=* : Only this instructor and their students (CRM instructor ID or import instructor_ref; repeatable)}
        {--only=all : Who to email: all, instructors or students}
        {--dry-run : List who would be emailed without sending anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the held welcome emails to users created by the Data Import page. Instructors get a password-setup link; students get their username and a temporary password. Safe to re-run — anyone already emailed is skipped.';

    /**
     * Execute the console command.
     */
    public function handle(ImportService $importService): int
    {
        $only = (string) $this->option('only');

        if (! in_array($only, ['all', 'instructors', 'students'], true)) {
            $this->error('--only must be one of: all, instructors, students.');

            return self::FAILURE;
        }

        $instructorIds = [];
        foreach ((array) $this->option('instructor') as $idOrRef) {
            $instructorId = $importService->resolveInstructorId((string) $idOrRef);

            if ($instructorId === null) {
                $this->error("Instructor '{$idOrRef}' not found (tried import ref and CRM ID).");

                return self::FAILURE;
            }

            $instructorIds[] = $instructorId;
        }

        $users = $importService->getPendingWelcomeUsers($instructorIds, $only);

        if ($users->isEmpty()) {
            $this->info('No imported users are waiting for a welcome email.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['User ID', 'Role', 'Name', 'Email'], $users->map(fn ($user) => [
                $user->id,
                $user->role->value,
                $user->name,
                $user->email,
            ])->all());
            $this->line(sprintf('Dry run — %d email(s) would be sent.', $users->count()));

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = [];

        $this->withProgressBar($users, function ($user) use ($importService, &$sent, &$failed): void {
            if ($importService->sendWelcomeEmail($user)) {
                $sent++;
            } else {
                $failed[] = [$user->id, $user->role->value, $user->email];
            }
        });

        $this->newLine(2);
        $this->info("Sent {$sent} welcome email(s).");

        if ($failed !== []) {
            $this->warn(count($failed).' failed and are still pending — re-run to retry:');
            $this->table(['User ID', 'Role', 'Email'], $failed);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
