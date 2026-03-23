<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ResourceResource;
use App\Services\ResourceApiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceController extends Controller
{
    public function __construct(
        protected ResourceApiService $resourceService
    ) {}

    /**
     * Return all published resources.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $resources = $this->resourceService->getPublishedResources();

        return ResourceResource::collection($resources);
    }
}
