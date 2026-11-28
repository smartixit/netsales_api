<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Documents\StoreOperationalDocumentArtifactRequest;
use App\Http\Requests\API\V1\Documents\StoreOperationalDocumentSnapshotRequest;
use App\Http\Resources\API\V1\Documents\OperationalDocumentSnapshotResource;
use App\Models\OperationalDocumentSnapshot;
use App\Services\OperationalDocumentSnapshotService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use OpenApi\Attributes as OA;

final class OperationalDocumentSnapshotController extends BaseController
{
    public function __construct(private readonly OperationalDocumentSnapshotService $snapshots) {}

    #[OA\Post(
        path: '/api/v1/documents/snapshots', summary: 'store', tags: ['Document Snapshots'], operationId: 'operationaldocumentsnapshot_store',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['document_type', 'document_id', 'network_id', 'template_id', 'template_version'], properties: [new OA\Property(property: 'document_type', type: 'string'), new OA\Property(property: 'document_id', type: 'integer'), new OA\Property(property: 'network_id', type: 'integer'), new OA\Property(property: 'pos_id', type: 'integer'), new OA\Property(property: 'template_id', type: 'string'), new OA\Property(property: 'template_version', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function store(StoreOperationalDocumentSnapshotRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $snapshot = $this->snapshots->create(
                $request->user(),
                $data['document_type'],
                (int) $data['document_id'],
                (int) $data['network_id'],
                isset($data['pos_id']) ? (int) $data['pos_id'] : null,
                $data['template_id'],
                $data['template_version']
            );

            return $this->successResponse(
                'تم تثبيت لقطة المستند الرسمية.',
                new OperationalDocumentSnapshotResource($snapshot),
                201
            );
        } catch (AuthorizationException $exception) {
            return $this->errorResponse($exception->getMessage(), [], 403);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('المستند أو النطاق المطلوب غير موجود.', [], 404);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/documents/snapshots/{snapshotUuid}/artifact', summary: 'storeArtifact', tags: ['Document Snapshots'], operationId: 'operationaldocumentsnapshot_storeArtifact',
        parameters: [new OA\Parameter(name: 'snapshotUuid', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['artifact'], properties: [new OA\Property(property: 'artifact', type: 'string', format: 'binary')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeArtifact(
        StoreOperationalDocumentArtifactRequest $request,
        string $snapshotUuid
    ): JsonResponse {
        try {
            $snapshot = $this->snapshots->storeArtifact(
                $request->user(),
                $snapshotUuid,
                $request->file('artifact')
            );

            return $this->successResponse(
                'تم حفظ ملف PDF الرسمي وربطه بالنسخة الثابتة.',
                new OperationalDocumentSnapshotResource($snapshot)
            );
        } catch (AuthorizationException $exception) {
            return $this->errorResponse($exception->getMessage(), [], 403);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('نسخة المستند غير موجودة.', [], 404);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/documents/snapshots/{snapshotUuid}/download', summary: 'download', tags: ['Document Snapshots'], operationId: 'operationaldocumentsnapshot_download',
        parameters: [new OA\Parameter(name: 'snapshotUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function download(string $snapshotUuid): StreamedResponse|JsonResponse
    {
        $snapshot = OperationalDocumentSnapshot::query()
            ->where('snapshot_uuid', $snapshotUuid)
            ->first();

        if (! $snapshot) {
            return $this->errorResponse('نسخة المستند غير موجودة.', [], 404);
        }

        try {
            // Re-run source authorization against the immutable source identity.
            $this->snapshots->authorizeSnapshot(
                request()->user(),
                $snapshot
            );
        } catch (AuthorizationException $exception) {
            return $this->errorResponse($exception->getMessage(), [], 403);
        }

        if (! $snapshot->artifact_path || ! Storage::disk('protected')->exists($snapshot->artifact_path)) {
            return $this->errorResponse('ملف المستند غير جاهز أو غير موجود.', [], 404);
        }

        return Storage::disk('protected')->download(
            $snapshot->artifact_path,
            $snapshot->snapshot_uuid.'.pdf',
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
