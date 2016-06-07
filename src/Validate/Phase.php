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
 * Class Phase
 * @package Lullabot\AMP\Validate
 *
 * This class does NOT exist in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The purpose of this class is for the Context class to define some constants to indicate whether its in a local or
 * global phase of hunting for errors. Local errors are those which you know something is instantly wrong. Global errors
 * are where you have to run through the whole document/whole html fragement before knowing something is wrong.
 */
class Phase
{
    const PRE_LOCAL_PHASE = 'PRE_LOCAL_PHASE';
    const LOCAL_PHASE = 'LOCAL_PHASE';
    const GLOBAL_PHASE = 'GLOBAL_PHASE';
    const UNKNOWN_PHASE = 'UNKNOWN_PHASE';
}
