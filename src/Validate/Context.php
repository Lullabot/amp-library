<?php

use Lullabot\AMP\Spec\ValidationResult;

class Context
{
    /** @var DOMElement */
    public $tag;

    public function recordMandatoryAlternativeSatisfied(/* @var string */
        $satisfied)
    {
        // @todo
    }

    public function addError($code, array $params, $spec_url, ValidationResult $validationResult)
    {
        // @todo
    }

    public function recordTagspecValidated(ParsedTagSpec $parsed_spec)
    {
        // @todo. Probably need to add $parsed_spec to an splobjectstorage?
    }
}
