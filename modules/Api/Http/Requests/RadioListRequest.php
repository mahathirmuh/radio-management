<?php

namespace Modules\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\RadioManagement\Services\RadioFilter;

/**
 * The filters of the page "Register Radio", for the list of radios and its Excel export. A filter of several values
 * takes a list (`type[]=1&type[]=2`) or comma separated values (`type=1,2`).
 */
class RadioListRequest extends FormRequest
{
    /** The filters that take several values. */
    private const LISTS = ['company', 'owner', 'department', 'type', 'template', 'placement', 'condition'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (self::LISTS as $key) {
            $value = $this->query($key);
            if (is_string($value)) {
                $this->query->set($key, array_values(array_filter(array_map('trim', explode(',', $value)), fn ($part) => $part !== '')));
            }
        }
    }

    public function rules(): array
    {
        return [
            /** SN, user/unit, document number, notes, Radio ID, or MDC code. */
            'search' => ['nullable', 'string', 'max:100'],
            /** "active" (default), "archived" or "all". */
            'scope' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            /** Company (user's), by id. */
            'company' => ['nullable', 'array'],
            'company.*' => ['integer'],
            /** Owning company (Pemilik Aset), by id. */
            'owner' => ['nullable', 'array'],
            'owner.*' => ['integer'],
            'department' => ['nullable', 'array'],
            'department.*' => ['integer'],
            /** Radio type, by id. */
            'type' => ['nullable', 'array'],
            'type.*' => ['integer'],
            /** Channel template, by id. */
            'template' => ['nullable', 'array'],
            'template.*' => ['integer'],
            'owner_group' => ['nullable', Rule::in(['mti', 'non_mti'])],
            'placement' => ['nullable', 'array'],
            'placement.*' => [Rule::enum(Placement::class)],
            'condition' => ['nullable', 'array'],
            'condition.*' => [Rule::enum(Condition::class)],
            /** One flag of the column "Cek" (see /references). */
            'issue' => ['nullable', Rule::in(array_keys(RadioCheckService::ISSUES))],
            'sort' => ['nullable', Rule::in(RadioFilter::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            /** 1 to 100, 25 when not given (the list only). */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** The request as RadioFilter reads it (it was written for the page: orderBy / orderDir). */
    public function forFilter(): self
    {
        $this->merge(['orderBy' => $this->input('sort'), 'orderDir' => $this->input('direction')]);

        return $this;
    }
}
