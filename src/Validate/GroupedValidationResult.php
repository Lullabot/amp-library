<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\ValidationResultStatus;

/**
 * Class GroupedValidationResult
 * @package Lullabot\AMP\Validate
 *
 * This class does not exist in the canonical validator [1].
 *
 * [1] See https://github.com/ampproject/amphtml/blob/main/validator/validator.js
 *     and https://github.com/ampproject/amphtml/tree/main/validator
 */
class GroupedValidationResult
{
    public $status = ValidationResultStatus::UNKNOWN;
    /** @var GroupedValidationError[] */
    public $grouped_validation_errors = [];
}
