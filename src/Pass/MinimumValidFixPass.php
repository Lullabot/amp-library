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
use Lullabot\AMP\Validate\SValidationError;
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;
use QueryPath\DOMQuery;

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
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://github.com/ampproject/amphtml/blob/master/spec/amp-boilerplate.md]
 * - The mandatory tag 'head > style[amp-boilerplate]' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://github.com/ampproject/amphtml/blob/master/spec/amp-boilerplate.md]
 * - The mandatory tag 'noscript > style[amp-boilerplate]' is missing or incorrect.
 * [code: MANDATORY_TAG_MISSING  category: MANDATORY_AMP_TAG_MISSING_OR_INCORRECT see: https://github.com/ampproject/amphtml/blob/master/spec/amp-boilerplate.md]
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
        'noscript > style[amp-boilerplate]' => ['html > head > noscript',
            '<style amp-boilerplate="">body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style>'],
        'amphtml engine v0.js script' => ['html > head',
            '<script async="" src="https://cdn.ampproject.org/v0.js"></script>'],
        'head > style[amp-boilerplate]' => ['html > head',
            '<style amp-boilerplate="">body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style>'],
    ];

    // CDATA components in head
    protected $cdata_components = [
        'noscript > style[amp-boilerplate]' => 'body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}',
        'head > style[amp-boilerplate]' => 'body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}'
    ];

    public function pass()
    {
        foreach ($this->components as $tagname => $details) {
            $parent_path = $details[0];
            $description = $details[1];
            if ($tagname == 'head') {
                $this->addTagIfNotExists($tagname, $parent_path, $description, true);
            } else {
                $this->addTagIfNotExists($tagname, $parent_path, $description);
            }
        }

        // Set the canonical path
        if (isset($this->options['canonical_path'])) {
            $this->head_components['link rel=canonical'] = ['html > head', "<link rel='canonical' href='{$this->options['canonical_path']}'></link>"];
        } else {
            $this->head_components['link rel=canonical'] = ['html > head', "<link rel='canonical' href='./unknown-canonical-path.html'></link>"];
        }

        // Now go ahead and create any head components
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if ($error->line === PHP_INT_MAX &&
                $error->code === ValidationErrorCode::MANDATORY_TAG_MISSING &&
                !empty($error->params[0]) &&
                isset($this->head_components[$error->params[0]]) &&
                !$error->resolved
            ) {
                $tag_description = $error->params[0];
                $tag_html = $this->head_components[$tag_description][1];
                $parent_path = $this->head_components[$tag_description][0];

                $this->q->find($parent_path)->append($tag_html);
                $error->addActionTaken(new ActionTakenLine($tag_description, ActionTakenType::TAG_ADDED));
                $error->resolved = true;
            }
        }

        // Fix any incorrect boilerplate CDATA
        $this->fixBoilerPlateCDATA();

        return [];
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
            /** @var SValidationError $error */
            foreach ($this->validation_result->errors as $error) {
                if ($this->skipError($error, $description)) {
                    continue;
                }

                if ($prepend) {
                    $this->q->find($parent_path)->prepend("<{$tagname}></{$tagname}>");
                } else {
                    $this->q->find($parent_path)->append("<{$tagname}></{$tagname}>");
                }

                $error->addActionTaken(new ActionTakenLine($tagname, ActionTakenType::TAG_ADDED));
                $error->resolved = true;
                break;
            }
        }
    }

    /**
     * @param SValidationError $error
     * @param string $tagname
     * @return bool
     */
    protected function skipError(SValidationError $error, $tagname)
    {
        if ($error->line !== PHP_INT_MAX ||
            $error->code !== ValidationErrorCode::MANDATORY_TAG_MISSING ||
            empty($error->params[0]) ||
            $error->params[0] !== $tagname
        ) {
            return true;
        }

        return false;
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
