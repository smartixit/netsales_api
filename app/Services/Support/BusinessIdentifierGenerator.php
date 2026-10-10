<?php

namespace App\Services\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Enterprise Business Identifier Generator
 *
 * A reusable, highly customizable service to generate unique business identifiers
 * across any project (e.g. Center Codes, Invoice Numbers, Student IDs, Order Reference Keys).
 *
 * Supported features:
 * - Pattern-based templates: "{PREFIX}-{ID}-{RANDOM}", "{PREFIX}/{YEAR}/{SEQUENCE}"
 * - Fluent builder API with customizable segments (Prefix, Suffix, ID padding, Random strings, Dates)
 * - Cryptographically secure random generation with configurable character sets
 * - Anti-collision database validation with pessimistic locks and SoftDeletes support
 * - Out-of-the-box presets for domain entities (Centers, etc.)
 */
class BusinessIdentifierGenerator
{
    protected ?string $prefix = null;

    protected ?string $suffix = null;

    protected string $separator = '-';

    protected ?string $idValue = null;

    protected int $idPadLength = 3;

    protected string $idPadChar = '0';

    protected int $randomLength = 5;

    protected ?string $pattern = null;

    protected ?string $dateFormat = null;

    // Database uniqueness settings
    protected ?string $uniqueModelClass = null;

    protected ?string $uniqueColumn = null;

    protected bool $uniqueIncludeTrashed = true;

    protected int $maxUniqueAttempts = 15;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Set the prefix string (e.g. 'CTR', 'INV').
     */
    public function prefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Set the suffix string.
     */
    public function suffix(?string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Set the separator character (default '-').
     */
    public function separator(string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Set a known ID or sequence number, with custom zero-padding.
     */
    public function id(int|string|null $id, int $padLength = 3, string $padChar = '0'): self
    {
        $this->idValue = $id !== null ? (string) $id : null;
        $this->idPadLength = $padLength;
        $this->idPadChar = $padChar;

        return $this;
    }

    /**
     * Automatically resolve next sequence ID from database table with pessimistic locking.
     */
    public function nextIdFrom(string $modelClass, ?string $idColumn = null, int $padLength = 3): self
    {
        /** @var Model $instance */
        $instance = new $modelClass;
        $table = $instance->getTable();
        $column = $idColumn ?? $instance->getKeyName();

        // Lock the table/row to get atomic next integer sequence
        $maxId = DB::table($table)->lockForUpdate()->max($column) ?? 0;
        $nextId = (int) $maxId + 1;

        return $this->id($nextId, $padLength);
    }

    /**
     * Configure the random segment length (generated using UUID).
     */
    public function random(int $length = 5): self
    {
        $this->randomLength = $length;

        return $this;
    }

    /**
     * Set a custom date/time format placeholder.
     */
    public function date(string $format = 'Y'): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Define a custom template pattern.
     * Placeholders: {PREFIX}, {SUFFIX}, {ID}, {SEQUENCE}, {RANDOM}, {YEAR}, {YY}, {MONTH}, {DAY}, {DATE}
     *
     * Example: "{PREFIX}-{ID}-{RANDOM}"
     */
    public function pattern(string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * Enforce uniqueness by checking against a model and column in database.
     */
    public function uniqueIn(
        string $modelClass,
        string $column,
        bool $includeTrashed = true,
        int $maxAttempts = 15
    ): self {
        $this->uniqueModelClass = $modelClass;
        $this->uniqueColumn = $column;
        $this->uniqueIncludeTrashed = $includeTrashed;
        $this->maxUniqueAttempts = $maxAttempts;

        return $this;
    }

    /**
     * Generate the final business code string.
     */
    public function generate(): string
    {
        $attempts = 0;

        do {
            $code = $this->compileCode();
            $attempts++;

            if (! $this->uniqueModelClass || ! $this->uniqueColumn) {
                return $code;
            }

            $isUnique = $this->isCodeUniqueInDatabase($code);

            if ($isUnique) {
                return $code;
            }
        } while ($attempts < $this->maxUniqueAttempts);

        throw new RuntimeException(
            "Failed to generate a unique business identifier after {$this->maxUniqueAttempts} attempts for {$this->uniqueModelClass}::{$this->uniqueColumn}."
        );
    }

    /**
     * Internal compilation of the code string from pattern or fluent segments.
     */
    protected function compileCode(): string
    {
        $formattedId = $this->idValue !== null
            ? str_pad($this->idValue, $this->idPadLength, $this->idPadChar, STR_PAD_LEFT)
            : '';

        $randomSegment = $this->generateRandomString($this->randomLength);

        // If template pattern is provided, replace placeholders
        if ($this->pattern !== null) {
            $replacements = [
                '{PREFIX}' => (string) ($this->prefix ?? ''),
                '{SUFFIX}' => (string) ($this->suffix ?? ''),
                '{ID}' => $formattedId,
                '{SEQUENCE}' => $formattedId,
                '{RANDOM}' => $randomSegment,
                '{YEAR}' => date('Y'),
                '{YY}' => date('y'),
                '{MONTH}' => date('m'),
                '{DAY}' => date('d'),
                '{DATE}' => $this->dateFormat ? date($this->dateFormat) : date('Ymd'),
            ];

            $result = strtr($this->pattern, $replacements);

            // Clean up double separators if prefix or id is empty
            if ($this->separator !== '') {
                $sepRegex = preg_quote($this->separator, '/');
                $result = preg_replace("/{$sepRegex}{2,}/", $this->separator, $result);
                $result = trim($result, $this->separator);
            }

            return $result;
        }

        // Standard segment composition: [PREFIX]-[ID]-[RANDOM]-[SUFFIX]
        $segments = [];

        if (! empty($this->prefix)) {
            $segments[] = $this->prefix;
        }

        if ($this->dateFormat !== null) {
            $segments[] = date($this->dateFormat);
        }

        if ($this->idValue !== null) {
            $segments[] = $formattedId;
        }

        if ($this->randomLength > 0) {
            $segments[] = $randomSegment;
        }

        if (! empty($this->suffix)) {
            $segments[] = $this->suffix;
        }

        return implode($this->separator, $segments);
    }

    /**
     * Generate cryptographically secure random segment directly from UUID.
     * Extracts uppercase alphanumeric mixed characters (hex letters A-F and numbers 0-9)
     * directly from UUID, strictly guaranteeing mixed letters and digits.
     */
    protected function generateRandomString(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        do {
            $uuid = strtoupper(str_replace('-', '', (string) Str::uuid()));
            while (strlen($uuid) < $length) {
                $uuid .= strtoupper(str_replace('-', '', (string) Str::uuid()));
            }
            $random = substr($uuid, 0, $length);
        } while ($length >= 2 && (! preg_match('/[A-F]/', $random) || ! preg_match('/[0-9]/', $random)));

        return $random;
    }

    /**
     * Check if the generated code is unique in the specified database table.
     */
    protected function isCodeUniqueInDatabase(string $code): bool
    {
        /** @var Model $model */
        $model = new $this->uniqueModelClass;
        $query = $model->newQuery()->where($this->uniqueColumn, $code);

        // If model uses SoftDeletes and we should include trashed records in the uniqueness check
        if ($this->uniqueIncludeTrashed && method_exists($model, 'withTrashed')) {
            $query->withTrashed();
        }

        return ! $query->exists();
    }

    // ========================================================
    // PRESETS FOR NETSALES & COMMON DOMAINS
    // ========================================================

    /**
     * Generate a Center Code following NetSales architectural rule:
     * Format: CTR-00{id}-random (e.g. CTR-001-A7KPM)
     */
    public static function forCenter(int $centerId, ?string $centerModelClass = null): string
    {
        $generator = self::make()
            ->prefix('CTR')
            ->id($centerId, 3, '0')
            ->random(5)
            ->separator('-');

        if ($centerModelClass !== null) {
            $generator->uniqueIn($centerModelClass, 'center_code', true);
        }

        return $generator->generate();
    }

    /**
     * Generate an elegant, unique Result Business Identifier.
     * Format: RES-00{center_id}-{random} (e.g. RES-001-K9X2M)
     */
    public static function forResult(int $centerId, ?string $resultModelClass = null): string
    {
        $generator = self::make()
            ->prefix('RES')
            ->id($centerId, 3, '0')
            ->random(6)
            ->separator('-');

        if ($resultModelClass !== null) {
            $generator->uniqueIn($resultModelClass, 'result_uuid', true);
        }

        return $generator->generate();
    }

    /**
     * Generate a generic business identifier from custom configuration array.
     */
    public static function generateCustom(array $config = []): string
    {
        $generator = self::make();

        if (isset($config['prefix'])) {
            $generator->prefix($config['prefix']);
        }

        if (isset($config['suffix'])) {
            $generator->suffix($config['suffix']);
        }

        if (isset($config['separator'])) {
            $generator->separator($config['separator']);
        }

        if (isset($config['id'])) {
            $pad = $config['id_pad_length'] ?? 3;
            $generator->id($config['id'], $pad);
        }

        if (isset($config['random_length'])) {
            $generator->random((int) $config['random_length']);
        }

        if (isset($config['pattern'])) {
            $generator->pattern($config['pattern']);
        }

        if (isset($config['date_format'])) {
            $generator->date($config['date_format']);
        }

        if (! empty($config['model']) && ! empty($config['column'])) {
            $includeTrashed = $config['include_trashed'] ?? true;
            $generator->uniqueIn($config['model'], $config['column'], $includeTrashed);
        }

        return $generator->generate();
    }
}
