<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdempotencyService
{
    /**
     * حساب التجزئة القانونية الموحدة (Canonical Request Hash) لجسم الطلب ومسار الواجهة.
     */
    public function computeCanonicalHash(string $endpoint, array $payload): string
    {
        $sortedPayload = $this->recursiveKsort($payload);

        return hash('sha256', $endpoint.'|'.json_encode($sortedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * حجز أو استرجاع مفتاح عدم التكرار ذرياً مع دعم التوكيلات الحبيبية (Fencing Tokens) ومهايعة الانتهاء.
     *
     * @return array{status: 'RESERVED'|'COMPLETED'|'IN_FLIGHT', id?: int, fencing_token?: int, response_code?: int, response_body?: array, response_headers?: array}
     */
    public function reserve(
        int $userId,
        string $tenantType,
        int $tenantId,
        string $endpoint,
        string $key,
        string $requestHash,
        int $leaseSeconds = 30
    ): array {
        $now = Carbon::now('Asia/Aden');
        $leaseExpiry = $now->copy()->addSeconds($leaseSeconds);

        // 1. محاولة الإدراج الأولي السريع
        try {
            $insertedId = DB::table('idempotency_keys')->insertGetId([
                'user_id' => $userId,
                'tenant_type' => $tenantType,
                'tenant_id' => $tenantId,
                'endpoint' => $endpoint,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'PENDING',
                'fencing_token' => 1,
                'lease_expires_at' => $leaseExpiry,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'status' => 'RESERVED',
                'id' => $insertedId,
                'fencing_token' => 1,
            ];
        } catch (QueryException $e) {
            // المفتاح موجود مسبقاً (Unique constraint violation)
            if (! str_contains($e->getMessage(), 'uq_idempotency_scope') && ! str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
        }

        // 2. فحص السجل القائم واسترجاع النتيجة أو الاستحواذ على حجز منتهٍ
        return DB::transaction(function () use ($userId, $tenantType, $tenantId, $endpoint, $key, $requestHash, $leaseExpiry): array {
            /** @var IdempotencyKey|null $existing */
            $existing = IdempotencyKey::where('user_id', $userId)
                ->where('tenant_type', $tenantType)
                ->where('tenant_id', $tenantId)
                ->where('endpoint', $endpoint)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                // سباق تنافسي غير اعتيادي
                return ['status' => 'IN_FLIGHT'];
            }

            // التحقق من تطابق جسم الطلب مع التجزئة الأصلية
            if ($existing->request_hash !== $requestHash) {
                throw ValidationException::withMessages([
                    'idempotency_key' => ['تم استخدام مفتاح عدم التكرار مسبقاً مع بيانات طلب مختلفة.'],
                ]);
            }

            // إذا كانت العملية مكتملة مسبقاً: إرجاع الاستجابة المخزنة فوراً
            if ($existing->status === 'COMPLETED') {
                return [
                    'status' => 'COMPLETED',
                    'id' => $existing->id,
                    'fencing_token' => $existing->fencing_token,
                    'response_code' => $existing->response_code,
                    'response_body' => $existing->response_body,
                    'response_headers' => $existing->response_headers,
                ];
            }

            // إذا كان الطلب قيد التنفيذ ولم ينته عقد إيجاره
            if ($existing->status === 'PENDING' && $existing->lease_expires_at->isFuture()) {
                return [
                    'status' => 'IN_FLIGHT',
                    'id' => $existing->id,
                ];
            }

            // إذا انتهى عقد الإيجار أو كانت العملية قابلة لإعادة المحاولة (FAILED_RETRYABLE)
            $newFencingToken = $existing->fencing_token + 1;
            $updated = DB::table('idempotency_keys')
                ->where('id', $existing->id)
                ->where('fencing_token', $existing->fencing_token)
                ->update([
                    'status' => 'PENDING',
                    'fencing_token' => $newFencingToken,
                    'lease_expires_at' => $leaseExpiry,
                    'updated_at' => now('Asia/Aden'),
                ]);

            if ($updated) {
                return [
                    'status' => 'RESERVED',
                    'id' => $existing->id,
                    'fencing_token' => $newFencingToken,
                ];
            }

            return ['status' => 'IN_FLIGHT'];
        });
    }

    /**
     * إتمام مفتاح عدم التكرار وحفظ الاستجابة بشرط سريان التوكيل (Fencing Token).
     */
    public function complete(
        int $id,
        int $fencingToken,
        int $responseCode,
        array $responseBody,
        ?array $responseHeaders = null
    ): bool {
        $affected = DB::table('idempotency_keys')
            ->where('id', $id)
            ->where('fencing_token', $fencingToken)
            ->update([
                'status' => 'COMPLETED',
                'response_code' => $responseCode,
                'response_body' => json_encode($responseBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'response_headers' => $responseHeaders ? json_encode($responseHeaders) : null,
                'updated_at' => now('Asia/Aden'),
            ]);

        return $affected > 0;
    }

    /**
     * وسم المفتاح بالفشل.
     */
    public function fail(int $id, int $fencingToken, bool $retryable = true): bool
    {
        $status = $retryable ? 'FAILED_RETRYABLE' : 'FAILED_TERMINAL';

        $affected = DB::table('idempotency_keys')
            ->where('id', $id)
            ->where('fencing_token', $fencingToken)
            ->update([
                'status' => $status,
                'updated_at' => now('Asia/Aden'),
            ]);

        return $affected > 0;
    }

    /**
     * ترتيب المفاتيح تصاعدياً بشكل متداخل لضمان التجزئة القانونية الموحدة.
     */
    protected function recursiveKsort(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveKsort($value);
            }
        }
        ksort($array);

        return $array;
    }
}

