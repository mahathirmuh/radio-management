<?php

namespace Modules\Api\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Http\Requests\RadioRequest;

/**
 * A radio as the API receives it: the rules and messages of the form (RadioRequest), with the API's answer to a
 * refusal: 422 with the messages by field ({"message": ..., "errors": {"dmr_id": [...]}}).
 */
class RadioApiRequest extends RadioRequest
{
    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator);
    }
}
