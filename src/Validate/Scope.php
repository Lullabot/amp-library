<?php

namespace Lullabot\AMP\Validate;

/**
 * Class Scope
 * @package Lullabot\AMP\Validate
 *
 * This class does NOT exist in validator.js (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The purpose of this class is to set some constants for the Context class so that it knows if its validating
 * a body html fragment, a whole html document or a head fragment.
 *
 */
class Scope
{
    const HEAD_SCOPE = 'head';
    const BODY_SCOPE = 'body';
    const HTML_SCOPE = 'html';
}
