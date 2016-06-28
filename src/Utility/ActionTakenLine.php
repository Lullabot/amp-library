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

namespace Lullabot\AMP\Utility;

class ActionTakenLine
{
    /** @var  integer */
    public $lineno;
    /** @var  string */
    public $tag_attr_description;
    /** @var  string */
    public $human_description;
    /** @var  string */
    public $context_string = '';
    /** @var string */
    public $action_taken;
    /** @var  number */
    public $time_stamp;

    /**
     * ActionTakenLine constructor.
     * @param string $tag_attr_description
     * @param string $action
     * @param string|int $lineno
     * @param string $context_string
     * @param string $error
     */
    public function __construct($tag_attr_description, $action, $lineno = PHP_INT_MAX, $context_string = '', $error = '')
    {
        $this->tag_attr_description = $tag_attr_description;
        $this->action_taken = $action;
        $this->lineno = $lineno;
        $this->time_stamp = microtime(true);
        $this->context_string = $context_string;
        if (empty($context_string)) {
            $this->human_description = "ACTION TAKEN: $tag_attr_description " . $this->action_taken;
        } else {
            $this->human_description = $context_string . " at line $lineno" . PHP_EOL;
            $this->human_description .= " ACTION TAKEN: $tag_attr_description " . $this->action_taken;
        }
        if (!empty($error)) {
            $this->human_description .= PHP_EOL. " ERROR: $error";
        }
    }
}
