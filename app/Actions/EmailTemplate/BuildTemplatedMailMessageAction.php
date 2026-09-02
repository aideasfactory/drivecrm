<?php

declare(strict_types=1);

namespace App\Actions\EmailTemplate;

use App\Mail\RenderedEmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class BuildTemplatedMailMessageAction
{
    public function __invoke(RenderedEmailTemplate $rendered): MailMessage
    {
        $message = (new MailMessage)->subject($rendered->subject);

        if (trim($rendered->greeting) !== '') {
            $message->greeting($rendered->greeting);
        }

        [$before, $after] = $this->splitOnActionButton($rendered->body);

        $this->appendLines($message, $before);

        if ($rendered->actionText && $rendered->actionUrl) {
            $message->action($rendered->actionText, $rendered->actionUrl);
        }

        if ($after !== null) {
            $this->appendLines($message, $after);
        }

        if (trim($rendered->salutation) !== '') {
            $message->salutation($rendered->salutation);
        }

        return $message;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitOnActionButton(string $body): array
    {
        if (! preg_match('/\{\{\s*action_button\s*\}\}/i', $body)) {
            return [$body, null];
        }

        $parts = preg_split('/\{\{\s*action_button\s*\}\}/i', $body, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function appendLines(MailMessage $message, string $text): void
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $message->line($line);
        }
    }
}
