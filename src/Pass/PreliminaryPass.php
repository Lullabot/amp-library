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

namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;
use QueryPath\DOMQuery;
use QueryPath\Exception;

/**
 * Class PreliminaryPass
 * @package Lullabot\AMP\Pass
 *
 */
class PreliminaryPass extends BasePass
{
    public function pass()
    {
        $query = $this->q->find($this->context->getErrorScope());

        $remove_these = $this->getRemovalSelectors();
        foreach ($remove_these as $remove_this) {
            try {
                $tags = $query->find($remove_this);
            } catch (Exception $e) {
                $this->context->addError("BAD_CSS_SELECTOR_FOR_USER_SUBMITTED_TAG_BLACKLIST '$remove_this'", [],
                    '', $this->validation_result);
                continue;
            }

            /** @var DOMQuery $el */
            foreach ($tags as $el) {
                /** @var \DOMElement $dom_el */
                $dom_el = $el->get(0);
                $context_string = $this->getContextString($dom_el);
                $lineno = $this->getLineNo($dom_el);
                $tagname = mb_strtolower($dom_el->tagName, 'UTF-8');
                $message = "$tagname tag matches CSS selector '$remove_this'";
                $this->addActionTaken(new ActionTakenLine($message, ActionTakenType::BLACKLISTED_TAG_REMOVED, $lineno, $context_string));
                $el->remove();
            }
        }

        return $this->transformations;
    }

    /**
     * @return array
     */
    protected function getRemovalSelectors()
    {
        $remove_these = [];
        if (isset($this->options['remove_selectors'])) {
            $remove_these = $this->options['remove_selectors'];
        }

        $matching_functions = $this->getMatchingFunctions();
        foreach ($matching_functions as $matching_function) {
            $remove_these = array_merge(call_user_func($matching_function), $remove_these);
        }

        return $remove_these;
    }

    /**
     * @return string[]
     */
    protected function getMatchingFunctions()
    {
        $functions = get_defined_functions();
        $user_functions = $functions['user'];
        $matching_functions = [];
        foreach ($user_functions as $user_function) {
            if (stripos($user_function, 'lullabot_amp_remove_selectors') !== false) {
                $matching_functions[] = $user_function;
            }
        }

        return $matching_functions;
    }
}
