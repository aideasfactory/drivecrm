<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\YearEndArchiveReadyMail;
use App\Models\Instructor;
use App\Models\User;
use App\Models\YearEndArchive;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function makeArchiveInstructorUser(): array
{
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);

    return [$user, $instructor];
}

beforeEach(function () {
    createHmrcTestSchema();
    Storage::fake((string) config('hmrc.year_end_archive.disk', 'local'));
});

it('downloads the zip from a signed email link without a session', function () {
    [, $instructor] = makeArchiveInstructorUser();

    $archive = YearEndArchive::factory()->ready()->create([
        'instructor_id' => $instructor->id,
        'tax_year_start' => 2025,
    ]);

    $disk = (string) config('hmrc.year_end_archive.disk', 'local');
    Storage::disk($disk)->put($archive->file_path, 'zip-bytes');

    $url = URL::temporarySignedRoute(
        'hmrc.archive.download',
        now()->addHours(24),
        ['archive' => $archive->id],
    );

    $this->get($url)
        ->assertOk()
        ->assertDownload('drive-tax-archive-2025-26.zip');
});

it('lets the owning instructor download without a signed url', function () {
    [$user, $instructor] = makeArchiveInstructorUser();

    $archive = YearEndArchive::factory()->ready()->create([
        'instructor_id' => $instructor->id,
        'tax_year_start' => 2024,
    ]);

    $disk = (string) config('hmrc.year_end_archive.disk', 'local');
    Storage::disk($disk)->put($archive->file_path, 'zip-bytes');

    $this->actingAs($user)
        ->get(route('hmrc.archive.download', $archive))
        ->assertOk()
        ->assertDownload('drive-tax-archive-2024-25.zip');
});

it('returns a clear 404 when the signed link is valid but the archive is not ready', function () {
    [, $instructor] = makeArchiveInstructorUser();

    $archive = YearEndArchive::factory()->create([
        'instructor_id' => $instructor->id,
        'status' => YearEndArchive::STATUS_QUEUED,
    ]);

    $url = URL::temporarySignedRoute(
        'hmrc.archive.download',
        now()->addHour(),
        ['archive' => $archive->id],
    );

    $this->get($url)
        ->assertNotFound()
        ->assertSee('This archive is not available', false);
});

it('returns a clear 404 when the zip file is missing from disk', function () {
    [, $instructor] = makeArchiveInstructorUser();

    $archive = YearEndArchive::factory()->ready()->create([
        'instructor_id' => $instructor->id,
    ]);

    $url = URL::temporarySignedRoute(
        'hmrc.archive.download',
        now()->addHour(),
        ['archive' => $archive->id],
    );

    $this->get($url)
        ->assertNotFound()
        ->assertSee('Archive file is missing', false);
});

it('forbids guests from unsigned download urls', function () {
    [, $instructor] = makeArchiveInstructorUser();

    $archive = YearEndArchive::factory()->ready()->create([
        'instructor_id' => $instructor->id,
    ]);

    $this->get(route('hmrc.archive.download', $archive))
        ->assertForbidden();
});

it('emails a signed link that points at the zip download route', function () {
    Mail::fake();

    [$user, $instructor] = makeArchiveInstructorUser();

    $archive = YearEndArchive::factory()->ready()->create([
        'instructor_id' => $instructor->id,
        'tax_year_start' => 2025,
    ]);

    $this->actingAs($user)
        ->from('/hmrc/archive')
        ->post(route('hmrc.archive.email-link', $archive))
        ->assertRedirect();

    Mail::assertSent(YearEndArchiveReadyMail::class, function (YearEndArchiveReadyMail $mail) use ($archive, $user) {
        expect($mail->hasTo($user->email))->toBeTrue();

        $html = $mail->render();
        expect($html)->toContain('/hmrc/archive/'.$archive->id.'/download')
            ->and($html)->toContain('signature=');

        return true;
    });
});
