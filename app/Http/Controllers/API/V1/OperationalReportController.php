<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\OperationalReportType;
use App\Http\Requests\API\V1\Reports\OperationalReportCatalogRequest;
use App\Http\Requests\API\V1\Reports\OperationalReportRequest;
use App\Http\Requests\API\V1\Reports\StoreOperationalReportExportRequest;
use App\Http\Requests\API\V1\Reports\StoreOperationalReportPresetRequest;
use Illuminate\Support\MessageBag;
use App\Jobs\GenerateOperationalReportExport;
use App\Models\OperationalReportExport;
use App\Models\OperationalReportPreset;
use App\Services\OperationalReportAuthorizationService;
use App\Services\Reports\OperationalReportFilterValidator;
use App\Services\Reports\OperationalReportProviderRegistry;
use App\Services\Reports\OperationalReportPresetValidator;
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
        protected OperationalReportProviderRegistry $providers,
        protected OperationalReportFilterValidator $filterValidator,
        protected OperationalReportPresetValidator $presetValidator
    ) {}

    public function catalog(OperationalReportCatalogRequest $request): JsonResponse
    {
        $user = $request->user();
        $definitions = [];

        foreach ($this->providers->all() as $provider) {
            $definition = $provider->definition();
            $type = $provider->type();
            try {
                $this->authorization->authorize($user, $type, (int) $request->validated('network_id'), $request->filled('pos_id') ? (int) $request->validated('pos_id') : null, false, false);
                $definition['available'] = true;
            } catch (AuthorizationException $exception) {
                $definition['available'] = false;
                $definition['unavailable_reason'] = $exception->getMessage();
            } catch (ModelNotFoundException) {
                $definition['available'] = false;
                $definition['unavailable_reason'] = 'الشبكة المطلوبة غير موجودة.';
            }
            $definitions[] = $definition;
        }

        return $this->successResponse('كتالوج التقارير المتاح ضمن نطاق العمل المحدد.', [
            'schema_version' => 1,
            'reports' => $definitions,
        ]);
    }

    public function show(OperationalReportRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $type = OperationalReportType::from($data['report_type']);
            $networkId = (int) $data['network_id'];
            $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
            $this->authorization->authorize($request->user(), $type, $networkId, $posId);
            $filterValues = $data['filters'] ?? [];
            $this->validateProviderFilters($type, $posId, $filterValues);
            $filters = $filterValues + [
                'per_page' => (int) ($data['per_page'] ?? 25),
                'cursor' => $data['cursor'] ?? null,
            ];
            $provider = $this->providers->get($type);
            $result = $provider->build($request->user(), $networkId, $posId, $filters);
            $result->setCollection(
                $result->getCollection()->map(
                    fn ($row): array => $provider->serializeExportRow($row)
                )
            );

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
            $filters = $data['filters'] ?? [];
            $this->validateProviderFilters($type, $posId, $filters);

            return $this->successResponse(
                'تم احتساب ملخص التقرير على كامل النتائج المطابقة.',
                $this->providers->get($type)->summary($request->user(), $networkId, $posId, $filters)
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
            $filters = $data['filters'] ?? [];
            $this->validateProviderFilters($type, $posId, $filters);
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



    public function presets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_type' => ['required', 'string'],
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = OperationalReportType::from($data['report_type']);
        $networkId = (int) $data['network_id'];
        $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
        $this->authorization->authorize($request->user(), $type, $networkId, $posId);

        $scopeKey = $posId === null ? 'network' : 'pos:'.$posId;
        $items = OperationalReportPreset::query()
            ->where('user_id', $request->user()->user_id)
            ->where('network_id', $networkId)
            ->where('scope_key', $scopeKey)
            ->where('report_type', $type)
            ->orderBy('name')
            ->get()
            ->map(fn (OperationalReportPreset $preset): array => $this->serializePreset($preset));

        return $this->successResponse('الإعدادات المحفوظة للتقرير.', $items);
    }

    public function storePreset(StoreOperationalReportPresetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = OperationalReportType::from($data['report_type']);
        $networkId = (int) $data['network_id'];
        $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
        $this->authorization->authorize($request->user(), $type, $networkId, $posId);

        $errors = $this->presetValidator->validate(
            $type->value,
            $posId,
            $data['criteria'],
            $data['presentation'],
            (int) $data['report_schema_version']
        );
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $scopeKey = $posId === null ? 'network' : 'pos:'.$posId;
        $preset = OperationalReportPreset::query()->create([
            'preset_uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->user_id,
            'network_id' => $networkId,
            'pos_id' => $posId,
            'scope_key' => $scopeKey,
            'report_type' => $type,
            'name' => trim($data['name']),
            'report_schema_version' => (int) $data['report_schema_version'],
            'preset_schema_version' => 1,
            'criteria' => $data['criteria'],
            'presentation' => $data['presentation'],
        ]);

        return $this->successResponse('تم حفظ إعداد التقرير.', $this->serializePreset($preset), 201);
    }

    public function updatePreset(StoreOperationalReportPresetRequest $request, string $presetUuid): JsonResponse
    {
        $data = $request->validated();
        $type = OperationalReportType::from($data['report_type']);
        $networkId = (int) $data['network_id'];
        $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
        $this->authorization->authorize($request->user(), $type, $networkId, $posId);

        $preset = OperationalReportPreset::query()
            ->where('preset_uuid', $presetUuid)
            ->where('user_id', $request->user()->user_id)
            ->where('network_id', $networkId)
            ->where('scope_key', $posId === null ? 'network' : 'pos:'.$posId)
            ->where('report_type', $type)
            ->firstOrFail();

        $errors = $this->presetValidator->validate(
            $type->value,
            $posId,
            $data['criteria'],
            $data['presentation'],
            (int) $data['report_schema_version']
        );
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $preset->fill([
            'name' => trim($data['name']),
            'report_schema_version' => (int) $data['report_schema_version'],
            'preset_schema_version' => 1,
            'criteria' => $data['criteria'],
            'presentation' => $data['presentation'],
        ])->save();

        return $this->successResponse('تم تحديث إعداد التقرير.', $this->serializePreset($preset->refresh()));
    }

    public function destroyPreset(Request $request, string $presetUuid): JsonResponse
    {
        $data = $request->validate([
            'report_type' => ['required', 'string'],
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = OperationalReportType::from($data['report_type']);
        $networkId = (int) $data['network_id'];
        $posId = isset($data['pos_id']) ? (int) $data['pos_id'] : null;
        $this->authorization->authorize($request->user(), $type, $networkId, $posId);

        $preset = OperationalReportPreset::query()
            ->where('preset_uuid', $presetUuid)
            ->where('user_id', $request->user()->user_id)
            ->where('network_id', $networkId)
            ->where('scope_key', $posId === null ? 'network' : 'pos:'.$posId)
            ->where('report_type', $type)
            ->firstOrFail();
        $preset->delete();

        return $this->successResponse('تم حذف إعداد التقرير.');
    }

    private function serializePreset(OperationalReportPreset $preset): array
    {
        return [
            'preset_schema_version' => $preset->preset_schema_version,
            'id' => $preset->preset_uuid,
            'name' => $preset->name,
            'report_id' => $preset->report_type->value,
            'report_schema_version' => $preset->report_schema_version,
            'criteria' => $preset->criteria,
            'presentation' => $preset->presentation,
            'created_at' => $preset->created_at?->toIso8601String(),
            'updated_at' => $preset->updated_at?->toIso8601String(),
        ];
    }

    private function validateProviderFilters(OperationalReportType $type, ?int $posId, array $filters): void
    {
        $errors = new MessageBag();
        $this->filterValidator->validate($type, $posId, $filters, $errors);

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages($errors->messages());
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
            'report_type_label' => $this->providers->get($export->report_type)->definition()['label'],
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
