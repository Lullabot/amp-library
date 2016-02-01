<?php
namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;
use Lullabot\AMP\Warning;

abstract class FixHTMLPass
{
    protected $q;
    protected $warnings = [];

    function __construct(DOMQuery $q)
    {
        $this->q = $q;
    }

    abstract function pass();

    protected function addWarning(Warning $w) {
        $this->warnings[] = $w;
    }
}
