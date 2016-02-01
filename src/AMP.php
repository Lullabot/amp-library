<?php

namespace Lullabot\AMP;

use QueryPath;

class AMP
{
    // We'll need to add discovery of passes etc. very basic for now
    public $passes = ['Lullabot\AMP\Pass\FixATagPass'];

    /** @var array */
    public $warnings = [];
    /** @var string */
    public $input_html = '';
    /** @var string */
    public $amp_html = '';

    public function loadHTML($html) {
        $this->input_html = $html;
        $this->warnings = [];
        $this->amp_html = '';
    }

    /**
     * Convert an HTML Fragment to AMP HTML
     * @return string
     */
    public function convertToAMP()
    {
        /** @var QueryPath\DOMQuery $qp */
        $qp = QueryPath::withHTML($this->input_html);

        $warnings = [];
        foreach ($this->passes as $pass) {
            $qp_branch = $qp->branch();
            // Run the pass
            // Each of the $qp objects are pointing to the same DOMDocument
            $warning = (new $pass($qp_branch))->pass();
            $this->warnings = array_merge($this->warnings, $warning);
        }

        $this->amp_html =  $qp->innerHTML();
        return $this->amp_html;
    }

    public function warnings_human()
    {
        $warning_text = '';
        foreach ($this->warnings as $warning) {
            $warning_text .= "<p>$warning->human_description</p>";
        }

        return $warning_text;
    }
}