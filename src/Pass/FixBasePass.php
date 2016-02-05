<?php
namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;
use Lullabot\AMP\Warning;
use Lullabot\AMP\Spec\ValidatorRules;

abstract class FixBasePass
{
    /** @var DOMQuery  */
    protected $q;
    /** @var array */
    protected $warnings = [];
    /** @var  ValidatorRules */
    protected $rules;

    function __construct(DOMQuery $q, ValidatorRules $rules)
    {
        $this->q = $q;
        $this->rules = $rules;
    }

    abstract function pass();

    protected function addWarning(Warning $w)
    {
        $this->warnings[] = $w;
    }

    protected function getSpecifications($tagname) {
        // @todo
    }
}
