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
    public function __construct(private readonly TenantPermissionService $tenantPermissions) {}
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
            'pos_repayment' => ['netsales.repayment.receipt.executive' => ['1.0.0']],
            'expense' => ['netsales.expense.voucher.executive' => ['1.0.0']],
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
            $currencyCode = $this->resolveCurrency($networkId, $posId);
            $hashableSnapshot = [
                'schema_version' => 1,
                'currency_code' => $currencyCode,
                'template_id' => $templateId,
                'template_version' => $templateVersion,
                'source_snapshot' => $payload,
            ];
            $encoded = json_encode(
                $hashableSnapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
            $snapshotHash = hash('sha256', $encoded);

            $existing = OperationalDocumentSnapshot::query()
                ->where('document_type', $documentType)
                ->where('document_id', $documentId)
                ->where('template_id', $templateId)
                ->where('template_version', $templateVersion)
                ->where('snapshot_sha256', $snapshotHash)
                ->first();
            if ($existing) {
                return $existing;
            }

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
                'snapshot_sha256' => $snapshotHash,
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
                // Idempotent retry: the first immutable artifact remains canonical.
                return $snapshot;
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
            ->with([
                'network',
                'pos',
                'request',
                'createdBy',
                'items' => fn ($query) => $query->lockForUpdate(),
                'items.voucherPackage',
            ])
            ->where('network_id', $networkId)
            ->when($posId !== null, fn ($query) => $query->where('pos_id', $posId))
            ->lockForUpdate()
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
            ->with([
                'network',
                'pos',
                'createdBy',
                'reversedBy',
                'allocations' => fn ($query) => $query->lockForUpdate(),
                'allocations.invoice' => fn ($query) => $query->lockForUpdate(),
            ])
            ->where('network_id', $networkId)
            ->when($posId !== null, fn ($query) => $query->where('pos_id', $posId))
            ->lockForUpdate()
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
            ->with([
                'network',
                'createdBy',
                'items' => fn ($query) => $query->lockForUpdate(),
                'attachments' => fn ($query) => $query->lockForUpdate(),
            ])
            ->where('network_id', $networkId)
            ->lockForUpdate()
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
        $hasPermission = $permission !== null && (
            $networkScope
                ? $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)
                : $this->tenantPermissions->allowsPos($user, (int) $posId, $permission)
        );

        if (! $hasPermission) {
            throw new AuthorizationException('لا تملك صلاحية إصدار نسخة رسمية من هذا المستند.');
        }
    }

    private function resolveCurrency(int $networkId, ?int $posId): string
    {
        $networkCurrency = \App\Models\NetworkWallet::query()
            ->where('network_id', $networkId)
            ->value('currency_code');

        $networkCurrency = is_string($networkCurrency) && $networkCurrency !== ''
            ? strtoupper($networkCurrency)
            : null;

        if ($posId !== null) {
            $posCurrency = PosWallet::query()
                ->where('network_id', $networkId)
                ->where('pos_id', $posId)
                ->value('currency_code');

            $posCurrency = is_string($posCurrency) && $posCurrency !== ''
                ? strtoupper($posCurrency)
                : null;

            if ($networkCurrency !== null &&
                $posCurrency !== null &&
                $networkCurrency !== $posCurrency) {
                throw ValidationException::withMessages([
                    'currency_code' => ['عملة محفظة الشبكة لا تطابق عملة محفظة نقطة البيع؛ لا يمكن إصدار مستند موحد العملة.'],
                ]);
            }

            if ($posCurrency !== null) {
                return $posCurrency;
            }
        }

        return $networkCurrency ?? 'YER';
    }}
