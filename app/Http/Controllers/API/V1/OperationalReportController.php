<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\OperationalReportType;
use App\Http\Requests\API\V1\Reports\OperationalReportRequest;
use App\Http\Requests\API\V1\Reports\StoreOperationalReportExportRequest;
use App\Jobs\GenerateOperationalReportExport;
use App\Models\OperationalReportExport;
use App\Services\OperationalReportAuthorizationService;
use App\Services\OperationalReportQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalReportController extends BaseController
{
    public function __construct(
        protected OperationalReportAuthorizationService $authorization,
        protected OperationalReportQueryService $queries
    ) {}

    public function show(OperationalReportRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $type = OperationalReportType::from($data['report_type']);
            $networkId = (int) $data['network_id'];
            $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
            $this->authorization->authorize($request->user(), $type, $networkId, $posId);
            $filters = array_intersect_key($data, array_flip(['from_date', 'to_date', 'sale_type', 'status', 'search', 'per_page', 'cursor']));
            $result = $this->queries->build($request->user(), $type, $networkId, $posId, $filters);

            return $this->successResponse('تم إنشاء بيانات التقرير بنجاح.', $result);
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('الشبكة المطلوبة غير موجودة.', [], 404);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }
    }

    public function summary(OperationalReportRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $type = OperationalReportType::from($data['report_type']);
            $networkId = (int) $data['network_id'];
            $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
            $this->authorization->authorize($request->user(), $type, $networkId, $posId);
            $filters = array_intersect_key(
                $data,
                array_flip(['from_date', 'to_date', 'sale_type', 'status', 'search'])
            );

            return $this->successResponse(
                'تم احتساب ملخص التقرير على كامل النتائج المطابقة.',
                $this->queries->summary($type, $networkId, $posId, $filters)
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('الشبكة المطلوبة غير موجودة.', [], 404);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }
    }

    public function storeExport(StoreOperationalReportExportRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $type = OperationalReportType::from($data['report_type']);
            $networkId = (int) $data['network_id'];
            $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
            $this->authorization->authorize($request->user(), $type, $networkId, $posId, true);
            $filters = array_intersect_key($data, array_flip(['from_date', 'to_date', 'sale_type', 'status', 'search']));
            $export = OperationalReportExport::query()->create([
                'export_uuid' => (string) Str::uuid(),
                'network_id' => $networkId,
                'pos_id' => $posId,
                'requested_by' => $request->user()->user_id,
                'report_type' => $type,
                'filters' => $filters,
                'status' => 'QUEUED',
                'expires_at' => now('Asia/Aden')->addDays(7),
            ]);
            GenerateOperationalReportExport::dispatch($export->report_export_id);

            return $this->successResponse('تم استلام طلب تجهيز التقرير وسيكون متاحاً بعد اكتمال المعالجة.', [
                'export_id' => $export->export_uuid,
                'status' => $export->status->value,
                'status_label' => $export->status->label(),
                'status_url' => route('api.v1.reports.exports.show', ['exportUuid' => $export->export_uuid]),
            ], 202);
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('الشبكة المطلوبة غير موجودة.', [], 404);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }
    }

    public function exportStatus(Request $request, string $exportUuid): JsonResponse
    {
        $export = OperationalReportExport::query()
            ->where('export_uuid', $exportUuid)
            ->where('requested_by', $request->user()->user_id)
            ->first();

        if (! $export) {
            return $this->errorResponse('طلب التصدير غير موجود.', [], 404);
        }

        try {
            $this->authorization->authorize(
                $request->user(),
                $export->report_type,
                (int) $export->network_id,
                $export->pos_id ? (int) $export->pos_id : null,
                true
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        }

        return $this->successResponse('حالة طلب التصدير.', [
            'export_id' => $export->export_uuid,
            'report_type' => $export->report_type->value,
            'report_type_label' => $export->report_type->label(),
            'status' => $export->status->value,
            'status_label' => $export->status->label(),
            'failure_code' => $export->failure_code,
            'record_count' => $export->record_count,
            'artifact_size' => $export->artifact_size,
            'expires_at' => $export->expires_at?->toIso8601String(),
            'download_url' => $export->status->value === 'READY'
                ? route('api.v1.reports.exports.download', ['exportUuid' => $export->export_uuid])
                : null,
        ]);
    }

    public function download(Request $request, string $exportUuid): StreamedResponse|JsonResponse
    {
        $export = OperationalReportExport::query()
            ->where('export_uuid', $exportUuid)
            ->where('requested_by', $request->user()->user_id)
            ->first();

        if (! $export) {
            return $this->errorResponse('ملف التصدير غير موجود.', [], 404);
        }

        try {
            $this->authorization->authorize(
                $request->user(),
                $export->report_type,
                (int) $export->network_id,
                $export->pos_id ? (int) $export->pos_id : null,
                true
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        }

        if ($export->status->value !== 'READY' || ! $export->artifact_path || $export->expires_at?->isPast()) {
            return $this->errorResponse('ملف التصدير غير جاهز أو انتهت صلاحيته.', [], 410);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('protected');
        if (! $disk->exists($export->artifact_path)) {
            return $this->errorResponse('ملف التصدير غير موجود.', [], 404);
        }

        return $disk->download($export->artifact_path, $export->export_uuid.'.csv', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
