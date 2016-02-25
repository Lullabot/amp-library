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
    public $all_tag_specs = [];
    /** @var ParsedTagSpec[] */
    public $tag_specs_by_dispatch = [];
}