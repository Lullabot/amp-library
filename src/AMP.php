<?php

namespace Lullabot\AMP;

use QueryPath;
use SebastianBergmann\Diff\Differ;
use Lullabot\AMP\Pass\BasePass;
use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Validate\ParsedValidatorRules;
use Lullabot\AMP\Validate\Scope;
use Lullabot\AMP\Spec\ValidationRulesFactory;
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
        'Lullabot\AMP\Pass\IframeTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\StandardScanPass',
        'Lullabot\AMP\Pass\StandardFixPass',
        'Lullabot\AMP\Pass\HtmlCommentPass',
    ];

    /** @var array */
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

    /** @var array */
    protected $component_js = [];
    /** @var array */
    protected $options;

    public function getComponentJs()
    {
        return $this->component_js;
    }

    public function getWarnings()
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
     * The most important job of this constructor is to instantiate an object of the \Lullabot\AMP\Spec\ValidationRules class
     * @see src/Spec/validator-generated.php
     */
    public function __construct()
    {
        $this->rules = ValidationRulesFactory::createValidationRules();
        // @todo put this somewhere separate as a global singleton
        $this->parsed_rules = new ParsedValidatorRules($this->rules);
    }

    /**
     * Calling this function "clears" the state of the AMP object.
     * It then "loads" up new HTML, that is ready for conversion with
     * AMP::convertToAmpHtml()
     *
     * @param string $html
     * @param array $options
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
        $this->context = new Context($this->scope);
    }

    /**
     * Calling this function "clears" the state of the AMP object and puts it into default mode
     * Call this function when you don't want anything remaining in the AMP Object
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
     * Convert an HTML Fragment to AMP HTML
     * @return string
     */
    public function convertToAmpHtml()
    {
        // If the tags were stripped out, would this be the same string?
        $no_tags = strip_tags($this->input_html) == $this->input_html;
        // If there are no tags and we're in BODY_SCOPE, there are no warnings and we just return
        // For full html scope the situation is more complicated, as we might still want some warnings
        if ($this->scope == Scope::BODY_SCOPE && $no_tags) {
            $this->amp_html = $this->input_html;
            return $this->amp_html;
        }

        /** @var QueryPath\DOMQuery $qp */
        $qp = QueryPath::withHTML($this->input_html, NULL, array('convert_to_encoding' => 'UTF-8'));

        foreach ($this->passes as $pass_name) {
            // This hack mainly to avoid an ugly warning given by QueryPath
            if (!$no_tags) {
                // This is the main case
                $qp_branch = $qp->branch();
            } else {
                $qp_branch = $qp;
            }
            // end hack

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
            if (!$no_tags) {
                // This is the main case
                $this->amp_html = $qp->top()->html5();
            } else {
                $this->amp_html = $this->input_html;
            }
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

    public function warningsHuman()
    {
        return '<pre>' . $this->warningsHumanText() . '</pre>';
    }

    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Differs from AMP::warningsHuman() in that it outputs warnings in Text and not HTML format
     *
     * @param bool $no_heading
     * @return string
     */
    public function warningsHumanText($no_heading = FALSE)
    {
        $warning_text = '';
        if (!$no_heading) {
            $warning_text .= PHP_EOL . 'AMP-HTML Validation Issues';
            $warning_text .= PHP_EOL . '--------------------------' . PHP_EOL;
        }

        $warning_text .= $this->getValidationWarnings();

        if (!empty($this->action_taken)) {
            if (!$no_heading) {
                $warning_text .= PHP_EOL . 'Fixes made based on validation issues discovered (see above)';
                $warning_text .= PHP_EOL . '------------------------------------------------------------' . PHP_EOL;
            }

            foreach ($this->action_taken as $warning) {
                $warning_text .= "- $warning->human_description" . PHP_EOL;
            }
        }
        return $warning_text;
    }
}
