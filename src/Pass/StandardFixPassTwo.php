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

use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Validate\GroupedValidationError;
use Lullabot\AMP\Validate\Phase;
use Lullabot\AMP\Validate\SValidationError;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Utility\ActionTakenType;
use Lullabot\AMP\Utility\ActionTakenLine;
use QueryPath\DOMQuery;
use Lullabot\AMP\Validate\Context;

/**
 * Class StandardScanPassTwo
 * @package Lullabot\AMP\Pass
 *
 */
class StandardFixPassTwo extends BasePass
{
    // CDATA components in head
    protected $cdata_components = [
        'noscript > style[amp-boilerplate]' => 'body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}',
        'head > style[amp-boilerplate]' => 'body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}',
        'noscript > style[amp-boilerplate] - old variant' => 'body {opacity: 1}',
        'head > style[amp-boilerplate] - old variant' => 'body {opacity: 0}'
    ];

    public function pass()
    {
        // If the tag was not still not validated and is in <head> remove it
        $local_context = new Context($this->context->getErrorScope(), $this->context->getOptions());

        $this->fixBoilerPlateCDATA();
        $this->fixMutuallyExclusiveAttrsError();

        // Set the phase to LOCAL_PHASE before starting out
        $local_context->setPhase(Phase::LOCAL_PHASE);

        /**
         * $this->grouped_validation_result is set at the end of the StandardPass
         * @var GroupedValidationError $error
         */
        foreach ($this->grouped_validation_result->grouped_validation_errors as $error) {
            // If tag does not exist or this error does not pertain to a LOCAL_PHASE skip
            if (empty($error->dom_tag) || empty($error->dom_tag->tagName) || $error->phase !== Phase::LOCAL_PHASE) {
                continue;
            }

            $test_validation_result = new SValidationResult();
            $test_validation_result->status = ValidationResultStatus::UNKNOWN;

            $local_context->attachDomTag($error->dom_tag);
            $tagname = mb_strtolower($error->dom_tag->tagName, 'UTF-8');
            $this->parsed_rules->validateTag($local_context, $tagname, $this->encounteredAttributes($error->dom_tag), $test_validation_result);
            $this->parsed_rules->validateTagOnExit($local_context, $test_validation_result);

            if ($test_validation_result->status == ValidationResultStatus::UNKNOWN) {
                $test_validation_result->status = ValidationResultStatus::PASS;
            }

            if ($test_validation_result->status !== ValidationResultStatus::PASS &&
                in_array('head', $local_context->getAncestorTagNames())
            ) {
                if (strtolower($error->dom_tag->tagName) !== 'style' || !$error->dom_tag->hasAttribute('amp-custom')) {
                    $error->dom_tag->parentNode->removeChild($error->dom_tag);
                    $tagname = mb_strtolower($error->dom_tag->tagName, 'UTF-8');
                    $error->addGroupActionTaken(new ActionTakenLine($tagname, ActionTakenType::TAG_REMOVED_FROM_HEAD_AFTER_REVALIDATE_FAILED));
                }
            }

            $local_context->detachDomTag();
        }

        return [];
    }

    /**
     * If there are any mutually exclusive attributes found, remove all except the first one
     */
    protected function fixMutuallyExclusiveAttrsError()
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if ($error->code === ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS &&
                !empty($error->params[1]) &&
                !$error->resolved &&
                !empty($error->dom_tag)
            ) {
                // $error->params[1] looks something like this ['src', 'data-videoid']
                $attributes = json_decode(str_replace("'", '"', $error->params[1]));
                if (!is_array($attributes) || count($attributes) < 2) {
                    continue;
                }

                // Remove all attributes except the first one
                unset($attributes[0]);
                foreach ($attributes as $num => $attr_name) {
                    $error->dom_tag->removeAttribute($attr_name);
                }
                $attributes_removed = implode(',', $attributes);
                $error->addActionTaken(new ActionTakenLine($attributes_removed, ActionTakenType::ATTRIBUTE_REMOVED_MUTUALLY_EXCLUSIVE));
                $error->resolved = true;
            }
        }
    }

    protected function fixBoilerPlateCDATA()
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if ($error->code === ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT &&
                !empty($error->params[0]) &&
                !$error->resolved &&
                !empty($error->dom_tag) &&
                !empty($this->cdata_components[$error->params[0]])
            ) {
                $tag_description = $error->params[0];
                $text_content = $this->cdata_components[$tag_description];

                // Modify the existing CDATA
                $el = new DOMQuery($error->dom_tag);
                $el->text($text_content);
                $error->addActionTaken(new ActionTakenLine($tag_description, ActionTakenType::CDATA_ADDED_MODIFIED));
                $error->resolved = true;
            }
        }
    }

}
