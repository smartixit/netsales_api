<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Network\NetworkWorkspaceOverviewRequest;
use App\Http\Resources\API\V1\Network\NetworkWorkspaceOverviewResource;
use App\Models\Network;
use App\Services\NetworkWorkspaceOverviewService;
use Illuminate\Http\JsonResponse;

class NetworkWorkspaceOverviewController extends BaseController
{
    public function __construct(
        protected NetworkWorkspaceOverviewService $overviewService
    ) {}

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
