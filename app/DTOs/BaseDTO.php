<?php

namespace App\DTOs;

use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base Data Transfer Object (DTO) for standardizing data flow between
 * Controllers, Services, and Repositories using PHP 8.2+ readonly semantics.
 */
abstract readonly class BaseDTO
{
    /**
     * Convert DTO properties to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_READONLY);
        $data = [];

        foreach ($properties as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Return array omitting null values (useful for partial updates/patches).
     *
     * @return array<string, mixed>
     */
    public function toFilteredArray(): array
    {
        return array_filter($this->toArray(), static fn ($value) => $value !== null);
    }
}
