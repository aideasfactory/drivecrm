<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Student\DeleteStudentAction;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HandPupilAccountToLearner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pupils:hand-account-to-learner
        {keep : Student ID that holds the orders/lessons (currently logs in with the parent\'s email)}
        {duplicate : Student ID of the learner\'s own empty account (deleted to free their email)}
        {--contact-first-name= : Parent/payer first name (defaults to the kept record\'s contact first name)}
        {--contact-surname= : Parent/payer surname (defaults to the learner\'s surname)}
        {--dry-run : Show what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give a pupil record that a parent set up with their own email to the learner: delete the learner\'s empty duplicate account, switch the login to the learner\'s email, name and existing password, sign the parent out, and make the parent the paying contact. Orders, lessons, payments and the Stripe card are not moved.';

    /**
     * Execute the console command.
     */
    public function handle(StudentService $studentService, DeleteStudentAction $deleteStudent): int
    {
        $keep = Student::with('user')->find($this->argument('keep'));
        $duplicate = Student::with('user')->find($this->argument('duplicate'));

        if (! $keep?->user || ! $duplicate?->user) {
            $this->error('Both students must exist and have a login.');

            return self::FAILURE;
        }

        if ($keep->is($duplicate)) {
            $this->error('Keep and duplicate are the same student.');

            return self::FAILURE;
        }

        $problems = $this->duplicateProblems($duplicate);

        if ($problems !== []) {
            $this->error('Nothing was changed. The duplicate account is not empty:');

            foreach ($problems as $problem) {
                $this->warn("  - {$problem}");
            }

            return self::FAILURE;
        }

        $parentEmail = $keep->user->email;
        $learnerEmail = $duplicate->user->email;

        // Read the raw hash before the delete overwrites it with a random one.
        $learnerLogin = DB::table('users')
            ->where('id', $duplicate->user_id)
            ->first(['password', 'email_verified_at']);

        $details = [
            'first_name' => $duplicate->first_name,
            'surname' => $duplicate->surname,
            'email' => $learnerEmail,
            'owns_account' => false,
            'contact_first_name' => $this->option('contact-first-name') ?: ($keep->contact_first_name ?: 'Parent'),
            'contact_surname' => $this->option('contact-surname') ?: $duplicate->surname,
            'contact_email' => $parentEmail,
        ];

        if ($keep->instructor_id !== $duplicate->instructor_id) {
            $this->warn("Note: the two records have different instructors (keep: {$keep->instructor_id}, duplicate: {$duplicate->instructor_id}). The kept record's instructor stays.");
        }

        $this->line("Delete empty student #{$duplicate->id} \"{$duplicate->first_name} {$duplicate->surname}\" (login {$learnerEmail}) — frees the email");
        $this->line("Update student #{$keep->id} \"{$keep->first_name} {$keep->surname}\":");
        $this->line("  name            {$keep->first_name} {$keep->surname} → {$details['first_name']} {$details['surname']}");
        $this->line("  login email     {$parentEmail} → {$learnerEmail}");
        $this->line('  owns account    '.($keep->owns_account ? 'yes' : 'no').' → no (payment emails go to the contact)');
        $this->line("  contact         {$keep->contact_first_name} {$keep->contact_surname} <{$keep->contact_email}> → {$details['contact_first_name']} {$details['contact_surname']} <{$parentEmail}>");
        $this->line("  password        parent's → learner's existing password (from #{$duplicate->id})");
        $this->line('  sign-ins        parent signed out of the app and web');
        $this->line('Unchanged: orders, lessons, lesson payments, Stripe customer '.($keep->user->stripe_customer_id ?? '(none)').', messages.');

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing was saved.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keep, $duplicate, $details, $learnerLogin, $studentService, $deleteStudent): void {
            $deleteStudent($duplicate);

            $studentService->update($keep, $details);

            // Copy the hash as-is (query builder, so the model's 'hashed' cast can't re-hash it).
            DB::table('users')->where('id', $keep->user_id)->update([
                'password' => $learnerLogin->password,
                'email_verified_at' => $learnerLogin->email_verified_at,
                'remember_token' => null,
                'expo_push_token' => null,
            ]);

            $keep->user->tokens()->delete();
            DB::table('sessions')->where('user_id', $keep->user_id)->delete();
        });

        $this->info("Done. {$details['first_name']} logs in to DRIVE+ with {$learnerEmail} and her existing password. Payment emails go to {$parentEmail}.");

        return self::SUCCESS;
    }

    /**
     * Anything on the duplicate that deleting it would hide.
     *
     * @return list<string>
     */
    private function duplicateProblems(Student $duplicate): array
    {
        $checks = [
            'orders' => DB::table('orders')->where('student_id', $duplicate->id)->count(),
            'checklist items' => DB::table('student_checklist_items')->where('student_id', $duplicate->id)->count(),
            'progress entries' => DB::table('student_progress')->where('student_id', $duplicate->id)->count(),
            'pickup points' => DB::table('student_pickup_points')->where('student_id', $duplicate->id)->count(),
            'mock tests' => DB::table('mock_tests')->where('student_id', $duplicate->id)->count(),
            'practical/theory tests' => DB::table('student_tests')->where('student_id', $duplicate->id)->count(),
            'messages' => DB::table('messages')->where('from', $duplicate->user_id)->orWhere('to', $duplicate->user_id)->count(),
        ];

        $problems = [];

        foreach ($checks as $label => $count) {
            if ($count > 0) {
                $problems[] = "{$count} {$label}";
            }
        }

        if ($duplicate->user->stripe_customer_id) {
            $problems[] = "has a Stripe customer ({$duplicate->user->stripe_customer_id})";
        }

        return $problems;
    }
}
