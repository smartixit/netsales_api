<?php

namespace App\Interfaces;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع ميزات النظام (Feature Repository Interface).
 */
interface FeatureRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * البحث عن ميزة عبر اسمها الفريد.
     */
    public function findByName(string $name): ?Feature;

    /**
     * تبديل حالة تفعيل الميزة (نشط / معطل).
     */
    public function toggleStatus(int $id): bool;

    /**
     * جلب كافة الميزات المفعلة في النظام.
     */
    public function getActiveFeatures(): Collection;
}
