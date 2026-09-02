<?php

declare(strict_types=1);

use App\Mail\ResendTestMail;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class);

test('the resend mailer is registered with a key from services config', function () {
    expect(config('mail.mailers.resend.transport'))->toBe('resend');
    expect(config('mail.mailers.resend'))->toHaveKey('key');
    expect(config('services.resend'))->toHaveKey('key');
});

test('the resend mailer can be resolved from the mail manager', function () {
    config(['services.resend.key' => 're_test_key']);

    $mailer = app('mail.manager')->mailer('resend');

    expect($mailer)->toBeInstanceOf(Mailer::class);
});

test('mail test-resend fails when the api key is missing', function () {
    config(['services.resend.key' => '']);

    $this->artisan('mail:test-resend', ['email' => 'test@example.com'])
        ->expectsOutputToContain('RESEND_API_KEY is empty')
        ->assertFailed();
});

test('resend test mail renders the sent timestamp', function () {
    $mail = new ResendTestMail('2026-09-02 14:00:00');

    $mail->assertHasSubject('Drive CRM — Resend test');
    $mail->assertSeeInHtml('Resend transport test from Drive CRM');
    $mail->assertSeeInHtml('2026-09-02 14:00:00');
});

test('mail test-resend sends via the resend mailer when the api key is set', function () {
    Mail::fake();
    config(['services.resend.key' => 're_test_key']);

    $this->artisan('mail:test-resend', [
        'email' => 'recipient@example.com',
        '--subject' => 'Resend connectivity check',
    ])
        ->expectsOutputToContain('Sending test email to recipient@example.com')
        ->expectsOutputToContain('Resend accepted the message')
        ->assertSuccessful();

    Mail::assertSent(ResendTestMail::class, function (ResendTestMail $mail) {
        return $mail->hasTo('recipient@example.com')
            && $mail->hasSubject('Resend connectivity check');
    });
});
