<?php

declare(strict_types=1);

use App\Enums\EmailTemplateKey;
use App\Enums\UserRole;
use App\Models\EmailTemplate;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    createEmailTemplateTestSchema();
    $this->withoutVite();
});

test('guests cannot view email templates', function () {
    $this->get(route('email-templates.index'))
        ->assertRedirect();
});

test('students cannot view email templates', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);

    $this->actingAs($user)
        ->get(route('email-templates.index'))
        ->assertForbidden();
});

test('students cannot edit email templates', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->put(route('email-templates.update', EmailTemplateKey::LearnerWelcome->value), [
            'subject' => 'Hacked subject',
            'body' => 'Hacked body',
        ])
        ->assertForbidden();
});

test('instructors cannot view email templates', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('email-templates.index'))
        ->assertRedirect();
});

test('owners can view instructor and learner email templates', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->get(route('email-templates.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EmailTemplates/Index')
            ->has('templates', count(EmailTemplateKey::cases()))
        );
});

test('owners can edit email copy without changing the template key', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)->get(route('email-templates.index'));

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($owner)
        ->put(route('email-templates.update', EmailTemplateKey::LearnerWelcome->value), [
            'subject' => 'A new welcome subject',
            'greeting' => 'Hello {{recipient_name}},',
            'body' => 'Custom body with {{temporary_password}}',
            'salutation' => 'Thanks',
            'action_text' => 'Open the app',
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success', 'Email template saved.');

    $this->assertDatabaseHas('email_templates', [
        'key' => EmailTemplateKey::LearnerWelcome->value,
        'subject' => 'A new welcome subject',
        'body' => 'Custom body with {{temporary_password}}',
        'action_text' => 'Open the app',
    ]);
});

test('email copy validation rejects an empty subject', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($owner)
        ->from(route('email-templates.index'))
        ->put(route('email-templates.update', EmailTemplateKey::LearnerWelcome->value), [
            'subject' => '',
            'body' => 'Body copy',
        ])
        ->assertSessionHasErrors(['subject']);
});

test('owners can restore an edited template to the default copy', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)->get(route('email-templates.index'));

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($owner)
        ->put(route('email-templates.update', EmailTemplateKey::LearnerWelcome->value), [
            'subject' => 'Edited subject',
            'greeting' => 'Hi',
            'body' => 'Edited body',
            'salutation' => 'Bye',
            'action_text' => 'Go',
        ]);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($owner)
        ->post(route('email-templates.restore', EmailTemplateKey::LearnerWelcome->value))
        ->assertRedirect()
        ->assertSessionHas('success', 'Email template restored to the default copy.');

    $template = EmailTemplate::query()
        ->where('key', EmailTemplateKey::LearnerWelcome->value)
        ->first();

    expect($template?->subject)->toBe('Welcome to {{app_name}}!')
        ->and($template?->body)->toContain('{{temporary_password}}');
});
