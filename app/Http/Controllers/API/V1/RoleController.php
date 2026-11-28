<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Services\PlatformRoleReferenceService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RoleController extends BaseController
{
    public function __construct(
        protected PlatformRoleReferenceService $platformRoleReferenceService,
    ) {}

    #[OA\Get(
        path: '/api/v1/roles', summary: 'index', tags: ['Roles'], operationId: 'index_RoleController',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function index(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع أدوار المنصة المتاحة بنجاح.',
            RoleResource::collection($this->platformRoleReferenceService->getActivePlatformRoles())
        );
    }
}
