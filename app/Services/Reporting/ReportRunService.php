<?php

namespace App\Services\Reporting;

use App\Jobs\BuildReportRunJob;
use App\Models\BillingAccount;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\Support\FileUploadService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReportRunService
{
    public function __construct(
        private ReportDefinitionRegistry $definitions,
        private ReportAuthorizationService $authorization,
        private FileUploadService $files
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function networkCatalog(User $actor, int $networkId): array
    {
        $available = [];
        foreach ($this->definitions->networkDefinitions() as $definition) {
            try {
                $this->authorization->authorizeNetworkDefinition($actor, $networkId, $definition);
                $available[] = $this->catalogItem($definition);
            } catch (AuthorizationException) {
                // Do not disclose reports outside the caller's network permissions.
            }
        }

        return $available;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function platformCatalog(User $actor): array
    {
        $available = [];
        foreach ($this->definitions->platformDefinitions() as $definition) {
            try {
                $this->authorization->authorizePlatformDefinition($actor, $definition);
                $available[] = $this->catalogItem($definition);
            } catch (AuthorizationException) {
                // Catalog contains only reports visible to this platform role.
            }
        }

        return $available;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function queueNetworkReport(
        User $actor,
        int $networkId,
        string $reportKey,
        array $filters
    ): ReportRun {
        $definition = $this->definitions->get('NETWORK', $reportKey);
        $this->authorization->authorizeNetworkDefinition($actor, $networkId, $definition);
        $normalizedFilters = $this->validateFilters($definition, $filters);

        if ($reportKey === 'billing-account-statement') {
            BillingAccount::query()
                ->where('billing_account_id', (int) $normalizedFilters['billing_account_id'])
                ->where('network_id', $networkId)
                ->firstOrFail();
        }

        $run = ReportRun::query()->create([
            'report_run_uuid' => (string) Str::uuid(),
            'scope_type' => 'NETWORK',
            'network_id' => $networkId,
            'requested_by' => $actor->user_id,
            'permission_key' => (string) $definition['permission'],
            'report_key' => $reportKey,
            'definition_version' => (string) $definition['definition_version'],
            'status' => 'QUEUED',
            'filters' => $normalizedFilters,
            'artifact_format' => (string) $definition['artifact_format'],
            'queued_at' => now('Asia/Aden'),
        ]);

        BuildReportRunJob::dispatch((int) $run->report_run_id)->afterCommit();

        return $run;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function queuePlatformReport(
        User $actor,
        string $reportKey,
        array $filters
    ): ReportRun {
        $definition = $this->definitions->get('PLATFORM', $reportKey);
        $this->authorization->authorizePlatformDefinition($actor, $definition);
        $normalizedFilters = $this->validateFilters($definition, $filters);

        $run = ReportRun::query()->create([
            'report_run_uuid' => (string) Str::uuid(),
            'scope_type' => 'PLATFORM',
            'network_id' => null,
            'requested_by' => $actor->user_id,
            'permission_key' => (string) $definition['permission'],
            'report_key' => $reportKey,
            'definition_version' => (string) $definition['definition_version'],
            'status' => 'QUEUED',
            'filters' => $normalizedFilters,
            'artifact_format' => (string) $definition['artifact_format'],
            'queued_at' => now('Asia/Aden'),
        ]);

        BuildReportRunJob::dispatch((int) $run->report_run_id)->afterCommit();

        return $run;
    }

    /**
     * @return CursorPaginator<int, ReportRun>
     */
    public function networkRuns(User $actor, int $networkId, int $perPage = 20): CursorPaginator
    {
        $this->authorization->authorizeNetworkReportHistory($actor, $networkId);

        return ReportRun::query()
            ->where('scope_type', 'NETWORK')
            ->where('network_id', $networkId)
            ->where('requested_by', (int) $actor->user_id)
            ->orderByDesc('report_run_id')
            ->cursorPaginate(min(max($perPage, 1), 100));
    }

    /**
     * @return CursorPaginator<int, ReportRun>
     */
    public function platformRuns(User $actor, int $perPage = 20): CursorPaginator
    {
        $this->authorization->authorizePlatformDefinition($actor, [
            'permission' => 'billing.report',
        ]);

        return ReportRun::query()
            ->where('scope_type', 'PLATFORM')
            ->whereNull('network_id')
            ->where('requested_by', (int) $actor->user_id)
            ->orderByDesc('report_run_id')
            ->cursorPaginate(min(max($perPage, 1), 100));
    }

    public function networkRun(User $actor, int $networkId, int $runId): ReportRun
    {
        $run = ReportRun::query()
            ->where('scope_type', 'NETWORK')
            ->where('network_id', $networkId)
            ->findOrFail($runId);

        $this->authorization->authorizeRun($actor, $run);

        return $run;
    }

    public function platformRun(User $actor, int $runId): ReportRun
    {
        $run = ReportRun::query()
            ->where('scope_type', 'PLATFORM')
            ->whereNull('network_id')
            ->findOrFail($runId);

        $this->authorization->authorizeRun($actor, $run);

        return $run;
    }

    public function toData(ReportRun $run): ReportRunData
    {
        $definition = $this->definitions->get($run->scope_type, $run->report_key);
        $artifactAvailable = $run->status === 'COMPLETED'
            && $run->artifact_path !== null
            && ($run->expires_at === null || ! $run->expires_at->isPast());

        $artifact = $artifactAvailable ? [
            'format' => $run->artifact_format,
            'bytes' => (int) $run->artifact_bytes,
            'sha256' => $run->artifact_sha256,
            'records_count' => (int) $run->records_count,
            'is_truncated' => (bool) $run->is_truncated,
            'expires_at' => $run->expires_at?->toDateTimeString(),
            'download_url' => $this->files->temporarySignedUrl((string) $run->artifact_path),
        ] : null;

        $error = $run->status === 'FAILED' ? [
            'code' => $run->error_code,
            'message' => $run->error_message,
        ] : null;

        return new ReportRunData(
            reportRunId: (int) $run->report_run_id,
            reportRunUuid: (string) $run->report_run_uuid,
            scopeType: (string) $run->scope_type,
            networkId: $run->network_id === null ? null : (int) $run->network_id,
            reportKey: (string) $run->report_key,
            reportTitle: (string) $definition['title'],
            definitionVersion: (string) $run->definition_version,
            status: (string) $run->status,
            filters: $run->filters ?? [],
            artifact: $artifact,
            resultMetadata: $run->result_metadata,
            error: $error,
            queuedAt: $run->queued_at->toDateTimeString(),
            startedAt: $run->started_at?->toDateTimeString(),
            completedAt: $run->completed_at?->toDateTimeString(),
        );
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function validateFilters(array $definition, array $filters): array
    {
        $schema = $definition['filters'] ?? [];
        $unknown = array_diff(array_keys($filters), array_keys($schema));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filters' => ['تحتوي المرشحات على حقول غير معتمدة لهذا التقرير: '.implode(', ', $unknown)],
            ]);
        }

        $rules = [];
        foreach ($schema as $key => $specification) {
            $fieldRules = ($specification['required'] ?? false)
                ? ['required']
                : ['sometimes', 'nullable'];

            switch ($specification['type']) {
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'integer':
                    $fieldRules[] = 'integer';
                    $fieldRules[] = 'min:1';
                    break;
                case 'string':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:'.(int) ($specification['max'] ?? 120);
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported report filter type.');
            }

            $rules[$key] = $fieldRules;
        }

        $validated = Validator::make($filters, $rules)->validate();

        if (! empty($validated['from']) && ! empty($validated['to']) &&
            CarbonImmutable::parse($validated['from'])->greaterThan(CarbonImmutable::parse($validated['to']))) {
            throw ValidationException::withMessages([
                'filters.to' => ['يجب أن يكون تاريخ النهاية مساوياً لتاريخ البداية أو لاحقاً له.'],
            ]);
        }

        if (! empty($validated['from_date']) && ! empty($validated['to_date']) &&
            CarbonImmutable::parse($validated['from_date'])->greaterThan(CarbonImmutable::parse($validated['to_date']))) {
            throw ValidationException::withMessages([
                'filters.to_date' => ['يجب أن يكون تاريخ النهاية مساوياً لتاريخ البداية أو لاحقاً له.'],
            ]);
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function catalogItem(array $definition): array
    {
        return [
            'key' => $definition['key'],
            'scope' => $definition['scope'],
            'title' => $definition['title'],
            'description' => $definition['description'],
            'definition_version' => $definition['definition_version'],
            'artifact_format' => $definition['artifact_format'],
            'filters' => $definition['filters'],
            'max_records' => (int) $definition['max_records'],
        ];
    }
}
