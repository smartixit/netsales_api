<?php

namespace App\Services\Reporting;

/**
 * Immutable read model passed from the reporting application service to its API resource.
 *
 * @phpstan-type ReportArtifact array{
 *     format: string,
 *     bytes: int,
 *     sha256: string|null,
 *     records_count: int,
 *     is_truncated: bool,
 *     expires_at: string|null,
 *     download_url: string|null
 * }
 * @phpstan-type ReportError array{code: string|null, message: string|null}
 */
final readonly class ReportRunData
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed>|null $artifact
     * @param array<string, mixed>|null $resultMetadata
     * @param array{code: string|null, message: string|null}|null $error
     */
    public function __construct(
        public int $reportRunId,
        public string $reportRunUuid,
        public string $scopeType,
        public ?int $networkId,
        public string $reportKey,
        public string $reportTitle,
        public string $definitionVersion,
        public string $status,
        public array $filters,
        public ?array $artifact,
        public ?array $resultMetadata,
        public ?array $error,
        public string $queuedAt,
        public ?string $startedAt,
        public ?string $completedAt,
    ) {}
}
