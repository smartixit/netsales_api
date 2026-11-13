<?php

namespace App\Http\Requests\API\V1\Reports;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOperationalReportPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', 'in:sales,expenses,network_wallet_statement,pos_wallet_statement,repayments,debt_aging,requests'],
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'report_schema_version' => ['required', 'integer', 'min:1'],
            'criteria' => ['required', 'array:filters'],
            'criteria.filters' => ['required', 'array', 'max:32'],
            'criteria.filters.*' => ['required', 'array:type,value'],
            'criteria.filters.*.type' => ['required', 'string', 'in:text,integer,decimal,date,date_range,boolean,choice,choices'],
            'criteria.filters.*.value' => ['present'],
            'presentation' => ['required', 'array:visible_column_ids,sorts,group_by_field_id,template,language_tag,page_profile_id,include_metrics,include_charts'],
            'presentation.visible_column_ids' => ['sometimes', 'array', 'max:100'],
            'presentation.visible_column_ids.*' => ['required', 'string', 'max:128'],
            'presentation.sorts' => ['sometimes', 'array', 'max:20'],
            'presentation.sorts.*' => ['required', 'array:field_id,direction'],
            'presentation.sorts.*.field_id' => ['required', 'string', 'max:128'],
            'presentation.sorts.*.direction' => ['required', 'in:ascending,descending'],
            'presentation.group_by_field_id' => ['nullable', 'string', 'max:128'],
            'presentation.template' => ['nullable', 'array:id,version'],
            'presentation.template.id' => ['required_with:presentation.template', 'string', 'max:128'],
            'presentation.template.version' => ['required_with:presentation.template', 'array:major,minor,patch'],
            'presentation.template.version.major' => ['required_with:presentation.template', 'integer', 'min:0'],
            'presentation.template.version.minor' => ['required_with:presentation.template', 'integer', 'min:0'],
            'presentation.template.version.patch' => ['required_with:presentation.template', 'integer', 'min:0'],
            'presentation.language_tag' => ['nullable', 'string', 'max:32'],
            'presentation.page_profile_id' => ['nullable', 'string', 'max:64'],
            'presentation.include_metrics' => ['required', 'boolean'],
            'presentation.include_charts' => ['required', 'boolean'],
        ];
    }
}
