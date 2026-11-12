<?php

namespace App\Services\Reports;

use App\Contracts\Reports\OperationalReportProvider;
use App\Enums\OperationalReportType;
use InvalidArgumentException;

final class OperationalReportProviderRegistry
{
    /**
     * @var array<string, OperationalReportProvider>
     */
    private array $providers = [];

    /**
     * @param  iterable<OperationalReportProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $key = $provider->type()->value;

            if (isset($this->providers[$key])) {
                throw new InvalidArgumentException("Duplicate operational report provider: {$key}.");
            }

            $this->providers[$key] = $provider;
        }

        foreach (OperationalReportType::cases() as $type) {
            if (! isset($this->providers[$type->value])) {
                throw new InvalidArgumentException("Missing operational report provider: {$type->value}.");
            }
        }
    }

    public function get(OperationalReportType|string $type): OperationalReportProvider
    {
        $key = $type instanceof OperationalReportType ? $type->value : $type;
        $provider = $this->providers[$key] ?? null;

        if ($provider === null) {
            throw new InvalidArgumentException("Unsupported operational report type: {$key}.");
        }

        return $provider;
    }

    public function find(string $type): ?OperationalReportProvider
    {
        return $this->providers[$type] ?? null;
    }

    /**
     * @return list<OperationalReportProvider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_map(
            static fn (OperationalReportProvider $provider): array => $provider->definition(),
            $this->all()
        );
    }
}
