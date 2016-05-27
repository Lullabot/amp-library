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
 * Class TagSpecDispatch
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the TagSpecDispatch class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 */
class TagSpecDispatch
{
    /** @var ParsedTagSpec[] */
    protected $all_tag_specs = [];
    /** @var ParsedTagSpec[] */
    protected $tag_specs_by_dispatch = [];

    /**
     * @param string $dispatch_key
     * @param ParsedTagSpec $parsed_tag_spec
     */
    public function registerDispatchKey($dispatch_key, ParsedTagSpec $parsed_tag_spec)
    {
        assert(!isset($this->tag_specs_by_dispatch[$dispatch_key]));
        $this->tag_specs_by_dispatch[$dispatch_key] = $parsed_tag_spec;
    }

    public function registerTagSpec(ParsedTagSpec $parsed_tag_spec)
    {
        $this->all_tag_specs[] = $parsed_tag_spec;
    }

    public function hasDispatchKeys()
    {
        return !empty($this->tag_specs_by_dispatch);
    }

    /**
     * @param string $attr_name
     * @param string $attr_value
     * @param string $mandatory_parent
     * @return ParsedTagSpec|null
     */
    public function matchingDispatchKey($attr_name, $attr_value, $mandatory_parent)
    {
        $dispatch_key = self::makeDispatchKey($attr_name, $attr_value, $mandatory_parent);
        if (isset($this->tag_specs_by_dispatch[$dispatch_key])) {
            return $this->tag_specs_by_dispatch[$dispatch_key];
        }

        $dispatch_key = self::makeDispatchKey($attr_name, $attr_value, "");
        if (isset($this->tag_specs_by_dispatch[$dispatch_key])) {
            return $this->tag_specs_by_dispatch[$dispatch_key];
        }

        return null;
    }

    /**
     * @param string $attr_name
     * @param string $attr_value
     * @param string $mandatory_parent
     * @return string
     *
     * Corresponds to the global javascript function makeDispatchKey() in canonical validator.
     */
    public static function makeDispatchKey($attr_name, $attr_value, $mandatory_parent)
    {
        return "$attr_name^$attr_value^$mandatory_parent";
    }

    /**
     * @return bool
     */
    public function hasTagSpecs()
    {
        return !empty($this->all_tag_specs);
    }

    public function allTagSpecs()
    {
        return $this->all_tag_specs;
    }
}
