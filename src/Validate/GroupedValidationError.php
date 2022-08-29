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

use Lullabot\AMP\Spec\ValidationError;
use Lullabot\AMP\Utility\ActionTakenLine;

/**
 * Class GroupedValidationError
 * @package Lullabot\AMP\Validate
 *
 * This class does not exist in the canonical validator [1].
 *
 * [1] See https://github.com/ampproject/amphtml/blob/main/validator/validator.js
 *     and https://github.com/ampproject/amphtml/tree/main/validator
 */
class GroupedValidationError
{
    /** @var string */
    public $context_string;
    /** @var \DOMElement */
    public $dom_tag;
    /** @var int */
    public $line;
    /** @var SValidationError[] */
    public $validation_errors = [];
    /** @var string */
    public $phase;
    /** @var ActionTakenLine $action_taken */
    public $action_taken = null;

    public function __construct($context_string, $line = PHP_INT_MAX, $dom_tag = null, $phase = Phase::LOCAL_PHASE)
    {
        $this->context_string = $context_string;
        $this->dom_tag = $dom_tag;
        $this->line = $line;
        $this->phase = $phase;
    }

    /**
     * @param ValidationError $validation_error
     */
    public function addValidationError($validation_error)
    {
        $this->validation_errors[] = $validation_error;
    }

    /**
     * @param ActionTakenLine $a
     */
    public function addGroupActionTaken(ActionTakenLine $a)
    {
        $this->action_taken = $a;
    }

}
