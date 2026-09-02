<?php

declare(strict_types=1);

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Mail\EmailTemplateInterpolator;
use Tests\TestCase;

uses(TestCase::class);

test('it replaces known placeholders and leaves surrounding copy intact', function () {
    $interpolator = new EmailTemplateInterpolator;

    $result = $interpolator->interpolate(
        'Hello {{recipient_name}}, welcome to {{app_name}}.',
        [
            'recipient_name' => 'Sam',
            'app_name' => 'Drive',
        ],
    );

    expect($result)->toBe('Hello Sam, welcome to Drive.');
});

test('unknown placeholders become empty strings so tokens are not leaked', function () {
    $interpolator = new EmailTemplateInterpolator;

    expect($interpolator->interpolate('Hello {{missing}}!', []))->toBe('Hello !');
});

test('the action button marker is left in place for the mail builder', function () {
    $interpolator = new EmailTemplateInterpolator;

    $result = $interpolator->interpolate(
        "Before\n{{action_button}}\nAfter",
        ['action_button' => 'should-not-replace'],
    );

    expect($result)->toBe("Before\n{{action_button}}\nAfter");
});

test('every catalogued email template key has a definition', function () {
    foreach (EmailTemplateKey::cases() as $key) {
        $definition = EmailTemplateCatalog::definition($key);

        expect($definition['key'])->toBe($key->value)
            ->and($definition['name'])->not->toBeEmpty()
            ->and($definition['subject'])->not->toBeEmpty()
            ->and($definition['body'])->not->toBeEmpty();
    }
});
