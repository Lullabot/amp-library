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
use Lullabot\AMP\Validate\SValidationError;
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;
use QueryPath\DOMQuery;
use Lullabot\AMP\Validate\Phase;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\AMP;

/**
 * Class MinimumValidFixPass
 * @package Lullabot\AMP\Pass
 *
 * Minimum fixes for:
 * GLOBAL WARNING
 * - The mandatory tag 'head' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
 * - The mandatory tag 'link rel=canonical' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
 * - The mandatory tag 'meta charset=utf-8' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
 * - The mandatory tag 'meta name=viewport' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
 * - The mandatory tag 'body' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
 * - The mandatory tag 'amphtml engine v0.js script' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://www.ampproject.org/docs/reference/spec.html#required-markup]
 * - The mandatory tag 'noscript enclosure for boilerplate' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://github.com/ampproject/amphtml/blob/main/docs/spec/amp-boilerplate.md]
 * - The mandatory tag 'head > style[amp-boilerplate]' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://github.com/ampproject/amphtml/blob/main/docs/spec/amp-boilerplate.md]
 * - The mandatory tag 'noscript > style[amp-boilerplate]' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://github.com/ampproject/amphtml/blob/main/docs/spec/amp-boilerplate.md]
 */
class MinimumValidFixPass extends BasePass
{
    // These are the outer, important components
    protected $components = [
        'head' => ['html', 'head'],
        'body' => ['html', 'body'],
        'noscript' => ['html > head', 'noscript enclosure for boilerplate']
    ];

    // Components in head
    protected $head_components = [
        'meta charset=utf-8' => ['html > head', '<meta charset="utf-8"></meta>'],
        'meta name=viewport' => ['html > head', '<meta name="viewport" content="width=device-width,minimum-scale=1"></meta>'],
        'noscript > style[amp-boilerplate] - old variant' => ['html > head > noscript', '<style>body {opacity: 1}</style>'],
        'noscript > style[amp-boilerplate]' => ['html > head > noscript', '<style amp-boilerplate="">body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style>'],
        'amphtml engine v0.js script' => ['html > head', '<script async="" src="https://cdn.ampproject.org/v0.js"></script>'],
        'head > style[amp-boilerplate]' => ['html > head', '<style amp-boilerplate="">body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style>'],
        'head > style[amp-boilerplate] - old variant' => ['html > head', '<style>body {opacity: 0}</style>']
    ];

    public function pass()
    {
        // Only run this pass if we have some errors that we can try to fix
        if ($this->noFixableGlobalErrors()) {
            return [];
        }

        // Add <head>, <body>, <noscript> tags if necessary
        foreach ($this->components as $tagname => $details) {
            $parent_path = $details[0];
            $description = $details[1];
            if ($tagname == 'head') {
                $this->addTagIfNotExists($tagname, $parent_path, $description, true);
            } else {
                $this->addTagIfNotExists($tagname, $parent_path, $description);
            }
        }

        // Re-validate document
        // Start revalidate
        $local_context = new Context($this->context->getErrorScope(), $this->context->getOptions());

        // Set the phase to LOCAL_PHASE before starting out
        $local_context->setPhase(Phase::LOCAL_PHASE);

        $temp_validation_result = new SValidationResult();
        $temp_validation_result->status = ValidationResultStatus::UNKNOWN;

        // Re-validate all the tags
        $all_tags = $this->q->find('*')->get();
        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            $local_context->attachDomTag($tag);
            $tagname = mb_strtolower($tag->tagName, 'UTF-8');
            $this->parsed_rules->validateTag($local_context, $tagname, $this->encounteredAttributes($tag), $temp_validation_result);
            $this->parsed_rules->validateTagOnExit($local_context, $temp_validation_result);
            $local_context->detachDomTag();
        }

        $temp_validation_result_global_errors = new SValidationResult();
        $temp_validation_result_global_errors->status = ValidationResultStatus::UNKNOWN;
        $this->parsed_rules->maybeEmitGlobalTagValidationErrors($local_context, $temp_validation_result_global_errors, $this);
        $this->parsed_rules->endValidation($temp_validation_result_global_errors);
        // End revalidate

        // Allow the canonical path to be inserted, if available
        if (isset($this->options['canonical_path'])) {
            $this->head_components['link rel=canonical'] = ['html > head', "<link rel='canonical' href='{$this->options['canonical_path']}'></link>"];
        }

        // Now go ahead and create any head components
        /** @var SValidationError $error */
        foreach ($temp_validation_result_global_errors->errors as $error) {
            if ($error->code == ValidationErrorCode::MANDATORY_TAG_MISSING &&
                !empty($error->params[0]) &&
                isset($this->head_components[$error->params[0]]) &&
                !$error->resolved
            ) {
                $tag_description = $error->params[0];
                $tag_html = $this->head_components[$tag_description][1];
                $parent_path = $this->head_components[$tag_description][0];

                // Add a new line after so that it looks good when being printed out
                $this->q->find($parent_path)->append($tag_html . PHP_EOL);
                $this->addActionTakenInCorrectLocation($tag_description, new ActionTakenLine($tag_description, ActionTakenType::TAG_ADDED));
                $error->resolved = true;
            }
        }

        // This block of code marks issues as resolved if they got fixed by the StandardFixPass or the StandardFixPassTwo
        /** @var SValidationError[] $current_global_warnings */
        $current_global_warnings = $this->getCurrentGlobalWarnings();
        foreach ($current_global_warnings as $error) {
            if ($error->code == ValidationErrorCode::MANDATORY_TAG_MISSING &&
                !empty($error->params[0]) &&
                !$error->resolved
            ) {
                if (!$this->findSameError($error->params[0], $temp_validation_result_global_errors->errors)) {
                    $error->addActionTaken(new ActionTakenLine('', ActionTakenType::ISSUE_RESOLVED));
                    $error->resolved = true;
                }
            }
        }

        return [];
    }

    /**
     * @param string $tag_description
     * @param SValidationError[] $global_errors
     * @return bool
     */
    protected function findSameError($tag_description, $global_errors)
    {
        /** @var SValidationError $error */
        foreach ($global_errors as $error) {
            if ($error->code == ValidationErrorCode::MANDATORY_TAG_MISSING &&
                !empty($error->params[0]) &&
                $tag_description == $error->params[0]
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return SValidationError[]
     */
    protected function getCurrentGlobalWarnings()
    {
        /** @var GroupedValidationError $error */
        foreach ($this->grouped_validation_result->grouped_validation_errors as $error) {
            if ($error->context_string == AMP::AMP_GLOBAL_WARNING) {
                return $error->validation_errors;
            }
        }

        return [];
    }

    /**
     * @param $tag_description
     * @param ActionTakenLine $a
     * @return bool
     */
    protected function addActionTakenInCorrectLocation($tag_description, ActionTakenLine $a)
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if ($this->skipError($error, $tag_description)) {
                continue;
            }

            $error->addActionTaken($a);
            $error->resolved = true;
            return true;
        }

        return false;
    }


    /**
     * @return bool
     */
    protected function noFixableGlobalErrors()
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if ($error->context_string == AMP::AMP_GLOBAL_WARNING &&
                in_array($error->code, [ValidationErrorCode::MANDATORY_TAG_MISSING, ValidationErrorCode::TAG_REQUIRED_BY_MISSING]) &&
                !empty($error->params[0])
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $tagname
     * @param string $parent_path
     * @param string $description
     * @param boolean $prepend
     */
    protected function addTagIfNotExists($tagname, $parent_path, $description, $prepend = false)
    {

        $full_tag_path = "$parent_path > $tagname"; // e.g. html > head
        // First check, if tag exists, if not, then create it
        if (!$this->q->find($full_tag_path)->count()) {
            // Add a new line so that it looks good when being printed out
            if ($prepend) {
                $this->q->find($parent_path)->prepend("<{$tagname}>" . PHP_EOL . "</{$tagname}>" . PHP_EOL);
            } else {
                $this->q->find($parent_path)->append("<{$tagname}>" . PHP_EOL . "</{$tagname}>" . PHP_EOL);
            }
            $this->addActionTakenInCorrectLocation($description, new ActionTakenLine($tagname, ActionTakenType::TAG_ADDED));
        }
    }

    /**
     * @param SValidationError $error
     * @param string $tagname
     * @return bool
     */
    protected function skipError(SValidationError $error, $tagname)
    {
        if ($error->phase !== Phase::GLOBAL_PHASE ||
            !in_array($error->code, [ValidationErrorCode::MANDATORY_TAG_MISSING, ValidationErrorCode::TAG_REQUIRED_BY_MISSING]) ||
            empty($error->params[0]) ||
            $error->params[0] !== $tagname
        ) {
            return true;
        }

        return false;
    }
}
