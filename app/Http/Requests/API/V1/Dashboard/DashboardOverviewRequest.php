<?php

namespace App\Http\Requests\API\V1\Dashboard;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب استعلام إحصائيات لوحة القيادة الموحدة (Dashboard Overview Request).
 *
 * يلتزم الصرامة المعمارية:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. توفير دوال مساعدة صريحة ومحمية من النوع (Type-safe getters).
 */
class DashboardOverviewRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => [
                'nullable',
                'string',
                'in:today,yesterday,this_week,this_month,this_year',
            ],
            'network_id' => [
                'nullable',
                'integer',
                'exists:networks,network_id',
            ],
            'pos_id' => [
                'nullable',
                'integer',
                'exists:pos,pos_id',
            ],
        ];
    }

    /**
     * الفترة الزمنية المطلوبة للمؤشرات (الافتراضي: today).
     */
    public function getPeriod(): string
    {
        return (string) $this->input('period', 'today');
    }

    /**
     * معرف الشبكة المستهدفة إن تم تمريره صراحة.
     */
    public function getNetworkId(): ?int
    {
        return $this->filled('network_id') ? (int) $this->input('network_id') : null;
    }

    /**
     * معرف نقطة البيع المستهدفة إن تم تمريره صراحة.
     */
    public function getPosId(): ?int
    {
        return $this->filled('pos_id') ? (int) $this->input('pos_id') : null;
    }
}
