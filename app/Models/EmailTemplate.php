<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'subject',
        'greeting',
        'body',
        'salutation',
        'action_text',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();
        $template = static::query()->where($field, $value)->first();

        if ($template) {
            return $template;
        }

        if ($field !== 'key') {
            return null;
        }

        $enum = EmailTemplateKey::tryFrom((string) $value);

        if (! $enum) {
            return null;
        }

        $definition = EmailTemplateCatalog::definition($enum);

        return static::query()->create([
            'key' => $definition['key'],
            'subject' => $definition['subject'],
            'greeting' => $definition['greeting'],
            'body' => $definition['body'],
            'salutation' => $definition['salutation'],
            'action_text' => $definition['action_text'],
        ]);
    }
}
