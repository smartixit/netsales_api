<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Network\NetworkWorkspaceOverviewRequest;
use App\Http\Resources\API\V1\Network\NetworkWorkspaceOverviewResource;
use App\Models\Network;
use App\Services\NetworkWorkspaceOverviewService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NetworkWorkspaceOverviewController extends BaseController
{
    public function __construct(
        protected NetworkWorkspaceOverviewService $overviewService
    ) {}

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/workspace-overview', summary: 'show', tags: ['Network Workspace'], operationId: 'networkworkspaceoverview_show',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function show(NetworkWorkspaceOverviewRequest $request, int $networkId): JsonResponse
    {
        $network = Network::query()->findOrFail($networkId);
        $overview = $this->overviewService->getOverview(
            $request->user(),
            $network,
            $request->period()
        );

        return $this->successResponse(
            'تم جلب ملخص مساحة عمل الشبكة بنجاح.',
            (new NetworkWorkspaceOverviewResource($overview))->resolve($request)
        );
    }
}
