<?php

declare(strict_types=1);

namespace App\Actions\Workflow;

use App\Enums\EmailTemplateKey;
use App\Mail\EmailTemplateCatalog;
use App\Support\WorkflowCatalog;

class ListWorkflowsAction
{
    /**
     * The workflow reference for the Workflows page. Each email's label is its
     * template name as shown on the Email templates page, with the workflow's
     * scenario (conditions that always hold there) and the other workflows
     * that send the same email.
     *
     * @return list<array{key: string, group: string, title: string, summary: string, scenario: list<string>, steps: list<array{when: string, what: string, messages: list<array{channel: string, template: ?string, label: string, recipient: string, note: ?string, scenario: list<string>, shared_with: list<array{key: string, title: string}>}>}>}>
     */
    public function __invoke(): array
    {
        $usage = WorkflowCatalog::templateUsage();

        return array_map(function (array $workflow) use ($usage): array {
            $workflow['steps'] = array_map(function (array $step) use ($workflow, $usage): array {
                $step['messages'] = array_map(
                    fn (array $message): array => $this->describe($message, $workflow, $usage),
                    $step['messages'],
                );

                return $step;
            }, $workflow['steps']);

            return $workflow;
        }, WorkflowCatalog::workflows());
    }

    /**
     * @param  array{channel: string, template: ?string, label: string, recipient: string, note: ?string}  $message
     * @param  array{key: string, scenario: list<string>}  $workflow
     * @param  array<string, list<array{key: string, title: string}>>  $usage
     * @return array{channel: string, template: ?string, label: string, recipient: string, note: ?string, scenario: list<string>, shared_with: list<array{key: string, title: string}>}
     */
    private function describe(array $message, array $workflow, array $usage): array
    {
        $message['scenario'] = [];
        $message['shared_with'] = [];

        $key = $message['template'] ? EmailTemplateKey::tryFrom($message['template']) : null;

        if ($key === null) {
            return $message;
        }

        $definition = EmailTemplateCatalog::definition($key);

        $message['label'] = $definition['name'];
        $message['scenario'] = array_values(array_intersect($workflow['scenario'], array_keys($definition['conditions'])));
        $message['shared_with'] = array_values(array_filter(
            $usage[$key->value] ?? [],
            fn (array $other): bool => $other['key'] !== $workflow['key'],
        ));

        return $message;
    }
}
