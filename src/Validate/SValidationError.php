<?php

namespace Lullabot\AMP\Validate;


use Lullabot\AMP\Spec\ValidationError;

/**
 * Class SValidationError
 * @package Lullabot\AMP\Validate
 *
 * This class does NOT exist in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * Adds some more information to the ValidationError class
 *
 */
class SValidationError extends ValidationError
{
    /** @var  \DOMElement */
    public $dom_tag = null;
    /** @var string */
    public $attr_name = '';
    public $phase = Phase::UNKNOWN_PHASE;
}
