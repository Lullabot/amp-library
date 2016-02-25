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
 * Class Scope
 * @package Lullabot\AMP\Validate
 *
 * This class does NOT exist in validator.js (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The purpose of this class is to set some constants for the Context class so that it knows if its validating
 * a body html fragment, a whole html document or a head fragment.
 *
 */
class Scope
{
    const HEAD_SCOPE = 'head';
    const BODY_SCOPE = 'body';
    const HTML_SCOPE = 'html';
}
