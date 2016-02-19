<?php

namespace Lullabot\AMP;

use Lullabot\AMP\Pass\BasePass;
use Lullabot\AMP\Spec\ValidatorRules;
use QueryPath;
use SebastianBergmann\Diff\Differ;
use Lullabot\AMP\Spec\ValidationRulesFactory;
use Lullabot\AMP\Spec\ValidationRules;

class AMP
{
    // We'll need to add discovery of passes etc. very basic for now
    // The Standard Pass should be first
    public $passes = [
        'Lullabot\AMP\Pass\StandardScanPass',
        'Lullabot\AMP\Pass\ImgTagPass',
        'Lullabot\AMP\Pass\HtmlCommentPass',
    ];

    /** @var array */
    protected $warnings = [];
    /** @var string */
    protected $input_html = '';
    /** @var string */
    protected $amp_html = '';
    /** @var ValidatorRules */
    protected $rules;
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
        return $this->warnings;
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
    }

    /**
     * Calling this function "clears" the state of the AMP object.
     * It then "loads" up new HTML, that is ready for conversion with
     * AMP::convertToAmpHtml()
     *
     * @param $html
     * @param $options
     */
    public function loadHtml($html, $options = [])
    {
        $this->clear();
        $this->input_html = $html;
        $this->options = $options;
    }

    /**
     * Calling this function "clears" the state of the AMP object.
     * Call this function when you don't want anything remaining in the AMP Object
     */
    public function clear()
    {
        $this->input_html = '';
        $this->warnings = [];
        $this->amp_html = '';
        $this->options = [];
        $this->component_js = [];
    }

    /**
     * Convert an HTML Fragment to AMP HTML
     * @return string
     */
    public function convertToAmpHtml()
    {
        /** @var QueryPath\DOMQuery $qp */
        $qp = QueryPath::withHTML($this->input_html, array('convert_to_encoding' => 'UTF-8'));

        $warnings = [];
        foreach ($this->passes as $pass_name) {
            $qp_branch = $qp->branch();
            // Each of the $qp objects are pointing to the same DOMDocument
            /** @var BasePass $pass */
            $pass = (new $pass_name($qp_branch, $this->rules, $this->options));
            // Run the pass
            $pass->pass();
            $this->warnings = array_merge($this->warnings, $pass->getWarnings());
            $this->component_js = array_merge($this->component_js, $pass->getComponentJs());
        }

        $this->sortWarningsByLineno();
        $this->amp_html = $qp->top()->html5();
        return $this->amp_html;
    }

    protected function sortWarningsByLineno()
    {
        // Sort the warnings according to increasing line number
        usort($this->warnings, function (Warning $warning1, Warning $warning2) {
            if ($warning1->lineno > $warning2->lineno) {
                return 1;
            } else if ($warning1->lineno < $warning2->lineno) {
                return -1;
            } else {
                return 0;
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
        // @todo: Remove body at some point?
        return $qp->top()->html5();
    }

    public function warningsHuman()
    {
        if (empty($this->warnings)) {
            return '';
        }

        $warning_text = '<div><strong>Warnings</strong></div><ul>';
        foreach ($this->warnings as $warning) {
            $warning_text .= "<li>$warning->human_description</li>";
        }
        $warning_text .= '</ul>';

        return $warning_text;
    }

    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Differs from AMP::warningsHuman() in that it outputs warnings in Text and not HTML format
     * @return string
     */
    public function warningsHumanText($no_heading = TRUE)
    {
        if (empty($this->warnings)) {
            return '';
        }

        $warning_text = '';
        if (!$no_heading) {
            $warning_text .= PHP_EOL . 'Warnings' . PHP_EOL;
        }
        foreach ($this->warnings as $warning) {
            $warning_text .= "- $warning->human_description" . PHP_EOL;
        }

        return htmlspecialchars_decode(strip_tags($warning_text));
    }
}
