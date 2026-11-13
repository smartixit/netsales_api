<?php

namespace App\Services\Reports;

use App\Contracts\Reports\OperationalReportProvider;
use App\Enums\OperationalReportType;
use App\Models\NetworkWallet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

abstract class AbstractOperationalReportProvider implements OperationalReportProvider
{
    abstract protected function label(): string;
    abstract protected function description(): string;
    abstract protected function viewPermission(): string;
    abstract protected function exportPermission(): string;
    abstract protected function scopes(): array;
    abstract protected function posSelection(): string;
    abstract protected function columns(): array;
    abstract protected function metricSlots(): array;

    protected function pageProfileIds(): array
    {
        return ['a4-portrait'];
    }

    protected function supportedLocaleTags(): array
    {
        return ['ar'];
    }
    abstract protected function summaryCharts(): array;
    abstract protected function statusChoices(): array;

    protected function statusFilterLabel(): string
    {
        return 'الحالة';
    }

    protected function saleTypeFilterLabel(): string
    {
        return 'نوع البيع';
    }

    protected function choiceLabel(string $filterId, string $value): string
    {
        return match ($filterId) {
            'sale_type' => match ($value) {
                'CASH' => 'نقدي',
                'CREDIT' => 'آجل',
                default => $value,
            },
            'status' => match ($value) {
                'PAID' => 'مدفوعة',
                'PARTIALLY_PAID' => 'مدفوعة جزئياً',
                'UNPAID' => 'غير مدفوعة',
                'PENDING' => 'قيد الانتظار',
                'APPROVED' => 'معتمدة',
                'REJECTED' => 'مرفوضة',
                'CANCELLED' => 'ملغاة',
                'DEPOSIT' => 'إيداع',
                'WITHDRAW' => 'سحب',
                'COMPLETED' => 'مكتمل',
                'REVERSED' => 'معكوس',
                default => $value,
            },
            default => $value,
        };
    }

    protected function saleTypeChoices(): array
    {
        return [];
    }

    public function definition(): array
    {
        $type = $this->type();
        $filters = [
            ['id' => 'from_date', 'label' => 'من تاريخ', 'kind' => 'date', 'required' => false],
            ['id' => 'to_date', 'label' => 'إلى تاريخ', 'kind' => 'date', 'required' => false],
            ['id' => 'search', 'label' => 'بحث', 'kind' => 'text', 'required' => false, 'max_length' => 120],
        ];
        if ($statuses = $this->statusChoices()) {
            $filters[] = ['id' => 'status', 'label' => $this->statusFilterLabel(), 'kind' => 'single_choice', 'required' => false, 'choices' => array_map(fn (string $value): array => ['value' => $value, 'label' => $this->choiceLabel('status', $value)], $statuses)];
        }
        if ($saleTypes = $this->saleTypeChoices()) {
            $filters[] = ['id' => 'sale_type', 'label' => $this->saleTypeFilterLabel(), 'kind' => 'single_choice', 'required' => false, 'choices' => array_map(fn (string $value): array => ['value' => $value, 'label' => $this->choiceLabel('sale_type', $value)], $saleTypes)];
        }
        $charts = $this->summaryCharts();

        return [
            'id' => $type->value,
            'schema_version' => 1,
            'title_key' => 'operational_reports.'.$type->value.'.title',
            'description_key' => 'operational_reports.'.$type->value.'.description',
            'supported_locale_tags' => $this->supportedLocaleTags(),
            'label' => $this->label(),
            'description' => $this->description(),
            'permissions' => ['view' => $this->viewPermission(), 'export' => $this->exportPermission()],
            'supported_scopes' => $this->scopes(),
            'pos_selection' => $this->posSelection(),
            'filters' => $filters,
            'columns' => array_map(
                static fn (array $column): array => array_merge([
                    'visible_by_default' => true,
                    'hideable' => true,
                    'sortable' => false,
                    'groupable' => false,
                ], $column),
                $this->columns()
            ),
            'metric_slots' => $this->metricSlots(),
            'chart_slots' => $charts,
            'templates' => [['id' => 'netsales.operational-report.executive', 'version' => '1.0.0']],
            'page_profile_ids' => $this->pageProfileIds(),
            'execution_policy' => 'server',
            'client_row_limit' => null,
            'charts_available' => $charts !== [],
            'export_formats' => ['CSV'],
        ];
    }

    protected function applyDates(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->when($filters['from_date'] ?? null, fn (Builder $q, string $date): Builder => $q->where($column, '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to_date'] ?? null, fn (Builder $q, string $date): Builder => $q->where($column, '<=', Carbon::parse($date)->endOfDay()));
    }

    protected function paginate(Builder $query, array $filters, string $dateColumn, string $key): CursorPaginator
    {
        return $query
            ->orderByDesc($dateColumn)
            ->orderByDesc($key)
            ->cursorPaginate((int) ($filters['per_page'] ?? 25), ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    protected function pattern(array $filters): ?string
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $search === '' ? null : '%'.$search.'%';
    }

    protected function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    protected function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;
    }

    protected function currencyCode(int $networkId): string
    {
        $currencyCode = NetworkWallet::withTrashed()
            ->where('network_id', $networkId)
            ->value('currency_code');

        return is_string($currencyCode) && $currencyCode !== ''
            ? strtoupper($currencyCode)
            : 'YER';
    }

    protected function amountValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $decimal = trim((string) $value);
        if (preg_match('/^([+-]?)(\\d+)(?:\\.(\\d+))?$/D', $decimal, $matches) !== 1) {
            throw new \\InvalidArgumentException('Invalid decimal amount.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new \\InvalidArgumentException('The amount exceeds the supported two decimal places.');
        }

        $whole = ltrim($matches[2], '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $isZero = $whole === '0' && $fraction === '00';
        $sign = ($matches[1] ?? '') === '-' && ! $isZero ? '-' : '';

        return $sign.$whole.'.'.$fraction;
    }

    protected function toMinorUnits(mixed $value): int
    {
        $decimal = $this->amountValue($value);
        $negative = str_starts_with($decimal, '-');
        $unsigned = $negative ? substr($decimal, 1) : $decimal;
        [$whole, $fraction] = explode('.', $unsigned, 2);
        $wholeUnits = (int) $whole;

        if ($wholeUnits > intdiv(PHP_INT_MAX - 99, 100)) {
            throw new \\OverflowException('The amount exceeds the supported integer range.');
        }

        $minorUnits = ($wholeUnits * 100) + (int) $fraction;

        return $negative ? -$minorUnits : $minorUnits;
    }

    protected function fromMinorUnits(int $minorUnits): string
    {
        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);

        return ($negative ? '-' : '')
            .intdiv($absolute, 100)
            .'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    protected function addMinorUnits(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new \\OverflowException('The ledger amount exceeds the supported integer range.');
        }

        return $left + $right;
    }

    protected function chartPoint(string $label, string|int|float $value, ?string $series = null): array
    {
        return array_filter(['label' => $label, 'value' => $value, 'series' => $series], static fn ($v) => $v !== null);
    }
}
