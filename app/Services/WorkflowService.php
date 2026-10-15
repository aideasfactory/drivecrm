<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Workflow\ListWorkflowsAction;

class WorkflowService extends BaseService
{
    public function __construct(
        protected ListWorkflowsAction $listWorkflows,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return ($this->listWorkflows)();
    }
}
