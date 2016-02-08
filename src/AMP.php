<?php

namespace Lullabot\AMP;

use Lullabot\AMP\Spec\ValidatorRules;
use QueryPath;
use SebastianBergmann\Diff\Differ;
use Lullabot\AMP\Spec\ValidationRulesFactory;
use Lullabot\AMP\Spec\ValidationRules;

class AMP
{
    // We'll need to add discovery of passes etc. very basic for now
    public $passes = [
        'Lullabot\AMP\Pass\FixTagsAndAttributesPass',
        'Lullabot\AMP\Pass\FixStandardPass',
        'Lullabot\AMP\Pass\FixATagsPass',
        'Lullabot\AMP\Pass\FixHtmlCommentsPass',
        'Lullabot\AMP\Pass\FixScriptTagsBodyPass',
        'Lullabot\AMP\Pass\FixStyleTagsBodyPass',
        'Lullabot\AMP\Pass\FixImgTagsPass'
    ];

    /** @var array */
    protected $warnings = [];
    /** @var string */
    protected $input_html = '';
    /** @var string */
    protected $amp_html = '';
    /** @var ValidatorRules */
    protected $rules;

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
     * Calling this function "resets" the state of the AMP object.
     * It "loads" up new HTML, that is ready for conversion with
     * AMP::convertToAmpHtml()
     *
     * @param $html
     * @param $options
     */
    public function loadHtml($html, $options = [])
    {
        $this->input_html = $html;
        $this->warnings = [];
        $this->amp_html = '';
        $this->options = $options;
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
        foreach ($this->passes as $pass) {
            $qp_branch = $qp->branch();
            // Run the pass
            // Each of the $qp objects are pointing to the same DOMDocument
            $warning = (new $pass($qp_branch, $this->rules, $this->options))->pass();
            $this->warnings = array_merge($this->warnings, $warning);
        }

        $this->sortWarningsByLineno();
        // @todo: Remove body at some point?
        $this->amp_html = $qp->find('body')->innerHTML();
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
        return $qp->find('body')->innerHTML();
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
