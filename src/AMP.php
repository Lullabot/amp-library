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

namespace Lullabot\AMP;

use QueryPath;
use SebastianBergmann\Diff\Differ;
use Lullabot\AMP\Pass\BasePass;
use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Validate\ParsedValidatorRules;
use Lullabot\AMP\Validate\Scope;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Validate\RenderValidationResult;

class AMP
{
    // The StandardScanPass should be first after all transform passes
    // The StandardFixPass should be after StandardScanPass
    public $passes = [
        'Lullabot\AMP\Pass\ImgTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\IframeYouTubeTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\IframeTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\InstagramTransformPass', // Transform pass
        'Lullabot\AMP\Pass\TwitterTransformPass', // Transform pass
        'Lullabot\AMP\Pass\StandardScanPass',
        'Lullabot\AMP\Pass\StandardFixPass',
        // Disable this for now. Canonical validator also does not seem to flagging conditional comments.
        // 'Lullabot\AMP\Pass\HtmlCommentPass',
    ];

    /** @var ActionTakenLine[] */
    protected $action_taken = [];
    /** @var string */
    protected $input_html = '';
    /** @var string */
    protected $amp_html = '';
    /** @var ValidatorRules */
    protected $rules;
    /** @var ParsedValidatorRules */
    protected $parsed_rules;
    /** @var Context */
    protected $context = null;
    /** @var  SValidationResult */
    protected $validation_result = null;
    /** @var string */
    protected $scope = Scope::BODY_SCOPE;
    /** @var string[] */
    protected $component_js = [];
    /** @var array */
    protected $options;

    public function getComponentJs()
    {
        return $this->component_js;
    }

    /**
     * @deprecated use getActionTaken
     * @return array
     */
    public function getWarnings()
    {
        return $this->action_taken;
    }

    public function getActionTaken()
    {
        return $this->action_taken;
    }

    public function getInputHtml()
    {
        return $this->input_html;
    }

    public function getAmpHtml()
    {
        return $this->amp_html;
    }

    /**
     * AMP constructor.
     *
     * @see src/Spec/validator-generated.php
     */
    public function __construct()
    {
        // The ParsedValidationRules object is expensive to create. So we maintain a global singleton
        // This way the AMP Object creation is actually cheap
        /** @var ParsedValidatorRules parsed_rules */
        $this->parsed_rules = ParsedValidatorRules::getSingletonParsedValidatorRules();
        /** @var ValidatorRules rules */
        $this->rules = $this->parsed_rules->rules;
    }

    /**
     * Calling this function "clears" the state of the AMP object.
     * It then "loads" up new HTML, that is ready for conversion with
     * AMP::convertToAmpHtml()
     *
     * @param string $html
     * @param array $options
     * @throws \Exception
     */
    public function loadHtml($html, $options = [])
    {
        $this->clear();
        // Deal with with some edge cases
        // Ideally we should just throw an exception if we're not passed a string but we can be a bit liberal for now
        if (is_null($html)) {
            $this->input_html = '';
        } else if (!is_string($html)) {
            // Try to convert it it to string
            $this->input_html = (string)$html;
        } else {
            // This is the main case
            $this->input_html = $html;
        }
        $this->options = $options;
        $this->scope = !empty($options['scope']) ? $options['scope'] : Scope::BODY_SCOPE;
        // Currently we only support these two scopes
        if (!in_array($this->scope, [Scope::HTML_SCOPE, Scope::BODY_SCOPE])) {
            throw new \Exception("Invalid or currently unsupported scope $this->scope");
        }
        $this->context = new Context($this->scope);
    }

    /**
     * Calling this function "clears" the state of the AMP object and puts it into default mode
     * Call this function when you don't want anything remaining in the AMP Object.
     *
     * Calling this function is optional.
     */
    public function clear()
    {
        $this->input_html = '';
        $this->action_taken = [];
        $this->amp_html = '';
        $this->options = [];
        $this->component_js = [];
        $this->validation_result = new SValidationResult();
        $this->validation_result->status = ValidationResultStatus::FAIL;
        $this->context = null;
        $this->scope = Scope::BODY_SCOPE;
    }

    /**
     * An HTML fragment is anything that can occur within a _body_ tag
     * This method makes an HTML fragment a full HTML document
     *
     * We need to do this to avoid encoding issues.
     * see https://github.com/technosophos/querypath/issues/94#issuecomment-8784564
     *
     * @param $body_fragment
     * @return string
     */
    protected function makeFragmentWhole($body_fragment)
    {
        $pre_html = '<!DOCTYPE html><html amp><head><meta charset="UTF-8"></head><body>';
        $post_html = '</body></html>';
        return $pre_html . $body_fragment . $post_html;
    }

    /**
     * Provide a bare HTML document
     * @param string
     * @return string
     */
    protected function bareDocument($insert)
    {
        $trimmed = trim($insert);
        if (empty($trimmed)) {
            $html = "<!DOCTYPE html><html></html>";
        } else {
            $html = "<!DOCTYPE html><html><body>$insert</body></html>";
        }
        return $html;
    }

    /**
     * Convert an HTML Fragment to AMP HTML
     * @return string
     * @throws \Exception
     */
    public function convertToAmpHtml()
    {
        /** @var QueryPath\DOMQuery $qp */
        if ($this->scope == Scope::BODY_SCOPE) {
            $document = $this->makeFragmentWhole($this->input_html);
        } else if ($this->scope == Scope::HTML_SCOPE) {
            $striped_html = strip_tags($this->input_html);
            if ($striped_html !== $this->input_html) { // main case
                $document = $this->input_html;
            } else {
                $document = $this->bareDocument($this->input_html);
            }
        } else {
            throw new \Exception("Invalid or currently unsupported scope $this->scope");
        }

        $qp = QueryPath::withHTML($document, NULL, ['convert_to_encoding' => 'UTF-8']);

        foreach ($this->passes as $pass_name) {
            $qp_branch = $qp->branch();

            // Each of the $qp objects are pointing to the same DOMDocument
            /** @var BasePass $pass */
            $pass = (new $pass_name($qp_branch, $this->context, $this->validation_result, $this->parsed_rules, $this->options));

            // Run the pass
            $pass->pass();
            $this->action_taken = array_merge($this->action_taken, $pass->getWarnings());
            $this->component_js = array_merge($this->component_js, $pass->getComponentJs());
        }

        $this->sortActionTakeByLineno();

        if ($this->scope == Scope::HTML_SCOPE) {
            $this->amp_html = $qp->top()->html5();
        } else {
            $this->amp_html = $qp->find($this->scope)->innerHTML5();
        }

        return $this->amp_html;
    }

    protected function sortActionTakeByLineno()
    {
        // Sort the warnings according to increasing line number
        // timestamp is the tie breaker
        usort($this->action_taken, function (ActionTakenLine $action_taken_1, ActionTakenLine $action_taken_2) {
            if ($action_taken_1->lineno > $action_taken_2->lineno) {
                return 1;
            } else if ($action_taken_1->lineno < $action_taken_2->lineno) {
                return -1;
            } else {
                $result = $action_taken_1->time_stamp < $action_taken_2->time_stamp ? -1 : 1;
                return $result;
            }
        });
    }

    public function getInputOutputHtmlDiff($escape_html = TRUE)
    {
        $diff = new Differ();
        $diff_html = $diff->diff($this->formatSource($this->input_html), $this->amp_html);
        if ($escape_html) {
            return htmlspecialchars($diff_html, ENT_QUOTES);
        } else {
            return $diff_html;
        }
    }

    /**
     * Quick and dirty way to format html
     * Need this if the incoming html is to be diffed to the output html in any logical way
     * @param $html
     * @return string
     */
    protected function formatSource($html)
    {
        /** @var QueryPath\DOMQuery $qp */
        $qp = \QueryPath::withHTML($html);
        if ($this->scope == Scope::HTML_SCOPE) {
            return $qp->top()->html5();
        } else {
            return $qp->find($this->scope)->innerHTML5();
        }
    }

    /**
     * @return string
     */
    public function getValidationWarnings()
    {
        /** @var RenderValidationResult $render_validation_result */
        $render_validation_result = new RenderValidationResult($this->parsed_rules->format_by_code);
        $filename = !empty($this->options['filename']) ? $this->options['filename'] : '';
        return $render_validation_result->renderValidationResult($this->validation_result, $filename);
    }

    /**
     * Use this instead of warningsHumanText() in case you want to call where html needs to be shown
     *
     * This method makes strings html friendly with special characters escaped e.g. "<" becomes "&lt;"
     *
     * @return string
     */
    public function warningsHumanHtml()
    {
        return htmlspecialchars($this->warningsHumanText());
    }

    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Differs from AMP::warningsHuman() in that it outputs warnings in Text and not HTML format
     *
     * Use warningsHumanHtml() if you want the string to be html friendly with special characters escaped.
     * e.g. "<" becomes "&lt;"
     *
     * @param bool $no_heading
     * @return string
     */
    public function warningsHumanText($no_heading = FALSE)
    {
        $warning_text = '';
        if (!empty($this->action_taken)) {
            if (!$no_heading) {
                $warning_text .= PHP_EOL . 'Transformations made from HTML tags to AMP custom tags';
                $warning_text .= PHP_EOL . '-------------------------------------------------------' . PHP_EOL;
            }

            /** @var ActionTakenLine $action_taken */
            foreach ($this->action_taken as $action_taken) {
                $warning_text .= PHP_EOL . "$action_taken->human_description" . PHP_EOL;
            }
        }

        if (!$no_heading) {
            $warning_text .= PHP_EOL . PHP_EOL . 'AMP-HTML Validation Issues and Fixes';
            $warning_text .= PHP_EOL . '-------------------------------------' . PHP_EOL;
        }

        $warning_text .= $this->getValidationWarnings();

        return $warning_text;
    }
}
