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


use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Spec\ValidationError;

/**
 * Class SValidationError
 * @package Lullabot\AMP\Validate
 *
 * This class does NOT exist in validator.js
 * (see https://github.com/ampproject/amphtml/blob/main/validator/validator.js )
 *
 * Adds some more information to the ValidationError class
 *
 */
class SValidationError extends ValidationError
{
    /** @var  \DOMElement */
    public $dom_tag = null;
    /** @var string */
    public $attr_name = '';
    /** @var string */
    public $segment = '';
    public $phase = Phase::UNKNOWN_PHASE;
    /** @var string */
    public $context_string = '';
    /** @var ActionTakenLine */
    public $action_taken = null;
    /** @var float */
    public $time_stamp;
    /** Was the error resolved? @var bool */
    public $resolved = false;

    public function __construct()
    {
        $this->time_stamp = microtime(true);
    }

    public function addActionTaken(ActionTakenLine $a)
    {
        $this->action_taken = $a;
    }

}
