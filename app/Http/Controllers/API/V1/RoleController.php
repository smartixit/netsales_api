<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Services\PlatformRoleReferenceService;
use Illuminate\Http\JsonResponse;

class RoleController extends BaseController
{
    public function __construct(
        protected PlatformRoleReferenceService $platformRoleReferenceService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع أدوار المنصة المتاحة بنجاح.',
            RoleResource::collection($this->platformRoleReferenceService->getActivePlatformRoles())
        );
    }
}
