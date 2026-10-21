<?php

namespace Modules\RadioManagement\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\RadioConflicts;

class RadioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('serial_number')) {
            $this->merge(['serial_number' => RadioConflicts::normalizeSerial($this->input('serial_number'))]);
        }
        if ($this->filled('user_name')) {
            $this->merge(['user_name' => trim(preg_replace('/\s+/', ' ', $this->input('user_name')))]);
        }
    }

    public function rules(): array
    {
        $radio = $this->route('radio');
        $creating = ! $radio instanceof Radio;
        $currentId = $creating ? null : $radio->id;

        // A radio imported with a duplicated / hex-F / reserved Radio ID, or a duplicated SN, must
        // stay editable: those checks only run when the value is being changed. The conflict stays
        // visible in the "Cek" column until someone resolves it.
        $sameId = ! $creating && $this->filled('dmr_id')
            && (int) $this->input('dmr_id') === (int) $radio->dmr_id;
        $sameSerial = ! $creating && $this->filled('serial_number')
            && $this->input('serial_number') === RadioConflicts::normalizeSerial($radio->serial_number);

        return [
            'dmr_id' => array_values(array_filter([
                'nullable', 'integer', 'min:1', 'max:' . config('radio.id_max'),
                $sameId ? null : $this->radioIdRule($currentId),
            ])),
            'serial_number' => array_values(array_filter([
                $creating ? 'required' : 'nullable', 'string', 'max:50',
                $sameSerial ? null : $this->serialRule($currentId),
            ])),
            'radio_type_id' => [
                $creating ? 'required' : 'nullable', 'integer', Rule::exists('radio_types', 'id'),
                $this->activeRule('radio_types', $creating ? null : $radio->radio_type_id),
            ],
            'owner_company_id' => [
                'nullable', 'integer', Rule::exists('radio_companies', 'id'),
                $this->activeRule('radio_companies', $creating ? null : $radio->owner_company_id),
            ],
            'user_name' => ['nullable', 'string', 'max:150', 'required_if:placement,' . Placement::DiUser->value],
            'department_id' => [
                'nullable', 'integer', Rule::exists('radio_departments', 'id'),
                $this->activeRule('radio_departments', $creating ? null : $radio->department_id),
            ],
            'company_id' => [
                'nullable', 'integer', Rule::exists('radio_companies', 'id'),
                $this->activeRule('radio_companies', $creating ? null : $radio->company_id),
            ],
            'channel_template_id' => [
                'nullable', 'integer', Rule::exists('radio_channel_templates', 'id'),
                $this->activeRule('radio_channel_templates', $creating ? null : $radio->channel_template_id),
            ],
            'last_programmed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'placement' => ['required', Rule::enum(Placement::class)],
            'condition' => ['required', Rule::enum(Condition::class)],
            'last_verified_at' => ['nullable', 'date', 'before_or_equal:today'],
            'document_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'review_done' => ['nullable', 'boolean'],
            'version' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        $fields = ['dmr_id', 'serial_number', 'radio_type_id', 'owner_company_id', 'user_name', 'department_id', 'company_id',
            'channel_template_id', 'last_programmed_at', 'placement', 'condition', 'last_verified_at', 'document_no', 'notes'];

        return array_combine($fields, array_map(fn (string $field) => __('RadioManagement::register.fields.' . $field), $fields));
    }

    public function messages(): array
    {
        $rules = ['required', 'required_if', 'before_or_equal', 'integer', 'max', 'exists'];

        return array_combine($rules, array_map(fn (string $rule) => __('RadioManagement::register.rules.' . $rule), $rules));
    }

    /** Same error shape as the base app: { message, errors: string[] }. */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => __('RadioManagement::register.messages.invalid'),
            'errors' => $validator->errors()->all(),
        ], 422));
    }

    private function radioIdRule(?int $currentId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currentId) {
            if ($message = app(RadioConflicts::class)->radioId((int) $value, $currentId)) {
                $fail($message);
            }
        };
    }

    private function serialRule(?int $currentId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currentId) {
            if ($message = app(RadioConflicts::class)->serial((string) $value, $currentId)) {
                $fail($message);
            }
        };
    }

    /** A master row switched to "nonaktif" can stay on a radio that already has it, but not be picked anew. */
    private function activeRule(string $table, mixed $currentValue): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table, $currentValue) {
            if ((int) $value === (int) $currentValue) {
                return;
            }
            if (DB::table($table)->where('id', $value)->where('is_active', false)->exists()) {
                $fail(__('RadioManagement::register.rules.inactive'));
            }
        };
    }
}
