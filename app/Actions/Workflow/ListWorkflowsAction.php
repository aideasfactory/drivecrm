<?php

declare(strict_types=1);

namespace App\Actions\Workflow;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Support\WorkflowCatalog;

class ListWorkflowsAction
{
    /**
     * The workflow reference for the Workflows page, with each email's label
     * set to its template name as shown on the Email templates page.
     *
     * @return list<array{key: string, group: string, title: string, summary: string, steps: list<array{when: string, what: string, messages: list<array{channel: string, template: ?string, label: string, recipient: string, note: ?string}>}>}>
     */
    public function __invoke(): array
    {
        return array_map(function (array $workflow): array {
            $workflow['steps'] = array_map(function (array $step): array {
                $step['messages'] = array_map(fn (array $message): array => $this->withTemplateName($message), $step['messages']);

                return $step;
            }, $workflow['steps']);

            return $workflow;
        }, WorkflowCatalog::workflows());
    }

    /**
     * @param  array{channel: string, template: ?string, label: string, recipient: string, note: ?string}  $message
     * @return array{channel: string, template: ?string, label: string, recipient: string, note: ?string}
     */
    private function withTemplateName(array $message): array
    {
        $key = $message['template'] ? EmailTemplateKey::tryFrom($message['template']) : null;

        if ($key !== null) {
            $message['label'] = EmailTemplateCatalog::definition($key)['name'];
        }

        return $message;
    }
}
