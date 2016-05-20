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

use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\AttrTriggerSpec;

/**
 * Class ParsedAttrTriggerSpec
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedAttrTriggerSpec class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 */
class ParsedAttrTriggerSpec
{
    /** @var AttrTriggerSpec */
    protected $spec;
    /** @var string */
    protected $attr_name;
    /** @var string|null */
    protected $if_value_regex = null;

    public function __construct(AttrSpec $attr_spec)
    {
        $this->spec = $attr_spec->trigger;
        assert(!empty($attr_spec->name));
        $this->attr_name = $attr_spec->name;

        if (!empty($this->spec) && !empty($this->spec->if_value_regex)) {
            $this->if_value_regex = "&(*UTF8){$this->spec->if_value_regex}&i";
        }
    }

    /**
     * @return boolean
     */
    public function hasIfValueRegex()
    {
        return $this->if_value_regex !== null;
    }

    /**
     * @return null|string
     */
    public function getIfValueRegex()
    {
        return $this->if_value_regex;
    }

    /**
     * @return null|string
     */
    public function getAttrName()
    {
        return $this->attr_name;
    }

    /**
     * @return AttrTriggerSpec|null
     */
    public function getSpec()
    {
        return $this->spec;
    }
}
