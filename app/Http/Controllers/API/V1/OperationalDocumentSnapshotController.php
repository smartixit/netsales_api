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

final class OperationalDocumentSnapshotController extends BaseController
{
    public function __construct(private readonly OperationalDocumentSnapshotService $snapshots) {}

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
