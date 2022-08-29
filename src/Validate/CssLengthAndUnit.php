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

/**
 * Class CssLengthAndUnit
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the Context class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/main/validator/validator.js )
 */
class CssLengthAndUnit
{
    /** @var bool */
    public $is_valid = false;
    /** @var bool */
    public $is_set = false;
    /** @var bool */
    public $is_auto = false;
    /** @var string */
    public $unit = 'px';

    /**
     * CssLengthAndUnit constructor.
     * @param string|null $input
     * @param boolean $allow_auto
     */
    public function __construct($input, $allow_auto)
    {
        if (empty($input)) {
            $this->is_valid = true;
        } else {
            $this->is_set = true;
            if ($input === 'auto') {
                $this->is_auto = true;
                $this->is_valid = $allow_auto;
            } else {
                $regex = '/^\d+(?:\.\d+)?(px|em|rem|vh|vw|vmin|vmax)?$/';
                $matches = [];
                if (preg_match($regex, $input, $matches)) {
                    $this->is_valid = true;
                    if (!empty($matches[1])) {
                        $this->unit = $matches[1];
                    } else {
                        $this->unit = 'px';
                    }
                }
            }
        }
    }
}

