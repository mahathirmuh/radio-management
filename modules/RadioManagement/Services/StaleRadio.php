<?php

namespace Modules\RadioManagement\Services;

use Illuminate\Validation\ValidationException;

/**
 * A change based on an older state of the radio than the one in the register: somebody else saved it in between.
 * A ValidationException like every other refusal of RadioService (the pages show its message as they always did); the
 * API answers it with 409 Conflict.
 */
class StaleRadio extends ValidationException
{
}
