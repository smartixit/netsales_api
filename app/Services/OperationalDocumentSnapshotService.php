<?php

namespace App\Services;

use App\Http\Resources\API\V1\Expense\ExpenseResource;
use App\Http\Resources\API\V1\Pos\PosRepaymentResource;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use App\Models\Expense;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\OperationalDocumentSnapshot;
use App\Models\Pos;
use App\Models\PosRepayment;
use App\Models\PosUser;
use App\Models\PosWallet;
use App\Models\SaleInvoice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OperationalDocumentSnapshotService
{
    public function create(
        User $user,
        string $documentType,
        int $documentId,
        int $networkId,
        ?int $requestedPosId,
        string $templateId,
        string $templateVersion
    ): OperationalDocumentSnapshot {
        $allowedTemplates = [
            'sale_invoice' => ['netsales.invoice.executive' => ['1.0.0']],
            'pos_repayment' => ['netsales.repayment.receipt' => ['1.0.0']],
            'expense' => ['netsales.expense.voucher' => ['1.0.0']],
        ];
        if (! in_array($templateVersion, $allowedTemplates[$documentType][$templateId] ?? [], true)) {
            throw ValidationException::withMessages([
                'template_id' => ['قالب المستند أو إصداره غير معتمد للإصدار الرسمي.'],
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $documentType,
            $documentId,
            $networkId,
            $requestedPosId,
            $templateId,
            $templateVersion
        ): OperationalDocumentSnapshot {
            $source = $this->resolveSource($documentType, $documentId, $networkId, $requestedPosId);
            $posId = $source['pos_id'];
            $this->authorize($user, $documentType, $networkId, $posId);

            $lastVersion = OperationalDocumentSnapshot::query()
                ->where('document_type', $documentType)
                ->where('document_id', $documentId)
                ->lockForUpdate()
                ->max('document_version');

            $payload = $source['payload'];
            $encoded = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
            $currencyCode = $this->resolveCurrency($networkId, $posId);

            return OperationalDocumentSnapshot::query()->create([
                'snapshot_uuid' => (string) Str::uuid(),
                'network_id' => $networkId,
                'pos_id' => $posId,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'document_version' => ((int) $lastVersion) + 1,
                'document_reference' => $source['reference'],
                'template_id' => $templateId,
                'template_version' => $templateVersion,
                'schema_version' => 1,
                'currency_code' => $currencyCode,
                'source_snapshot' => $payload,
                'snapshot_sha256' => hash('sha256', $encoded),
                'issued_at' => now('Asia/Aden'),
                'created_by' => $user->user_id,
            ]);
        }, 3);
    }

    public function storeArtifact(
        User $user,
        string $snapshotUuid,
        UploadedFile $artifact
    ): OperationalDocumentSnapshot {
        return DB::transaction(function () use ($user, $snapshotUuid, $artifact): OperationalDocumentSnapshot {
            $snapshot = OperationalDocumentSnapshot::query()
                ->where('snapshot_uuid', $snapshotUuid)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize($user, $snapshot->document_type, (int) $snapshot->network_id, $snapshot->pos_id ? (int) $snapshot->pos_id : null);

            if ($snapshot->artifact_path !== null) {
                throw ValidationException::withMessages([
                    'artifact' => ['تم تثبيت ملف هذه النسخة ولا يمكن استبداله. أنشئ نسخة مستندية جديدة لإصدار ملف آخر.'],
                ]);
            }

            $path = 'operational-documents/'.$snapshot->network_id.'/'.$snapshot->document_type.'/'.$snapshot->snapshot_uuid.'.pdf';
            $storedPath = Storage::disk('protected')->putFileAs(
                dirname($path),
                $artifact,
                basename($path)
            );
            if ($storedPath === false) {
                throw new \RuntimeException('DOCUMENT_ARTIFACT_STORAGE_FAILED');
            }

            $snapshot->forceFill([
                'artifact_path' => $storedPath,
                'artifact_sha256' => hash_file('sha256', $artifact->getRealPath()),
                'artifact_size' => $artifact->getSize(),
            ])->save();

            return $snapshot->fresh();
        }, 3);
    }

    public function authorizeSnapshot(User $user, OperationalDocumentSnapshot $snapshot): void
    {
        $this->authorize(
            $user,
            $snapshot->document_type,
            (int) $snapshot->network_id,
            $snapshot->pos_id ? (int) $snapshot->pos_id : null
        );
    }

    private function resolveSource(string $type, int $id, int $networkId, ?int $posId): array
    {
        return match ($type) {
            'sale_invoice' => $this->invoiceSource($id, $networkId, $posId),
            'pos_repayment' => $this->repaymentSource($id, $networkId, $posId),
            'expense' => $this->expenseSource($id, $networkId, $posId),
            default => throw ValidationException::withMessages([
                'document_type' => ['نوع المستند غير مدعوم.'],
            ]),
        };
    }

    private function invoiceSource(int $id, int $networkId, ?int $posId): array
    {
        $invoice = SaleInvoice::query()
            ->with(['network', 'pos', 'request', 'createdBy', 'items.voucherPackage'])
            ->where('network_id', $networkId)
            ->when($posId !== null, fn ($query) => $query->where('pos_id', $posId))
            ->findOrFail($id);

        return [
            'pos_id' => $invoice->pos_id ? (int) $invoice->pos_id : null,
            'reference' => $invoice->invoice_uuid,
            'payload' => SaleInvoiceResource::make($invoice)->resolve(request()),
        ];
    }

    private function repaymentSource(int $id, int $networkId, ?int $posId): array
    {
        $repayment = PosRepayment::query()
            ->with(['network', 'pos', 'allocations.invoice', 'createdBy', 'reversedBy'])
            ->where('network_id', $networkId)
            ->when($posId !== null, fn ($query) => $query->where('pos_id', $posId))
            ->findOrFail($id);

        return [
            'pos_id' => (int) $repayment->pos_id,
            'reference' => $repayment->repayment_uuid,
            'payload' => PosRepaymentResource::make($repayment)->resolve(request()),
        ];
    }

    private function expenseSource(int $id, int $networkId, ?int $posId): array
    {
        if ($posId !== null) {
            throw new AuthorizationException('المصروفات التشغيلية تتبع نطاق الشبكة ولا تقبل نطاق نقطة البيع.');
        }

        $expense = Expense::query()
            ->with(['network', 'createdBy', 'items', 'attachments'])
            ->where('network_id', $networkId)
            ->findOrFail($id);

        return [
            'pos_id' => null,
            'reference' => $expense->expense_uuid,
            'payload' => ExpenseResource::make($expense)->resolve(request()),
        ];
    }

    private function authorize(User $user, string $documentType, int $networkId, ?int $posId): void
    {
        $network = Network::query()->findOrFail($networkId);
        $networkScope = (int) $network->owner_id === (int) $user->user_id
            || NetworkUser::query()->where('network_id', $networkId)
                ->where('user_id', $user->user_id)->where('status', 'ACTIVE')->exists();

        if ($posId !== null) {
            $activeLink = NetworkPos::query()->where('network_id', $networkId)
                ->where('pos_id', $posId)->where('status', true)->exists();
            if (! $activeLink) {
                throw new AuthorizationException('نقطة البيع غير مرتبطة بهذه الشبكة أو أن الارتباط غير نشط.');
            }
        }

        if (! $networkScope) {
            if ($documentType === 'expense' || $posId === null) {
                throw new AuthorizationException('لا تملك عضوية الشبكة اللازمة للوصول إلى هذا المستند.');
            }
            $posOwner = Pos::query()->where('pos_id', $posId)->where('owner_id', $user->user_id)->exists();
            $posMember = PosUser::query()->where('pos_id', $posId)
                ->where('user_id', $user->user_id)->where('status', 'ACTIVE')->exists();
            if (! $posOwner && ! $posMember) {
                throw new AuthorizationException('لا توجد عضوية نشطة تخولك الوصول إلى المستند.');
            }
        }

        $permission = match ($documentType) {
            'sale_invoice' => 'sales.view',
            'pos_repayment' => 'pos_wallets.view',
            'expense' => 'expenses.view',
            default => null,
        };
        $tenantId = $networkScope ? $networkId : (int) $posId;
        if ($permission === null || ! $user->hasPermission($permission, $tenantId)) {
            throw new AuthorizationException('لا تملك صلاحية إصدار نسخة رسمية من هذا المستند.');
        }
    }

    private function resolveCurrency(int $networkId, ?int $posId): string
    {
        if ($posId !== null) {
            $currency = PosWallet::query()->where('network_id', $networkId)
                ->where('pos_id', $posId)->value('currency_code');
            if (is_string($currency) && $currency !== '') {
                return $currency;
            }
        }

        $currency = \App\Models\NetworkWallet::query()
            ->where('network_id', $networkId)->value('currency_code');

        return is_string($currency) && $currency !== ''
            ? $currency
            : 'YER';
    }
}
