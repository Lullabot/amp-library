<?php

namespace Lullabot\AMP\Validate;

/**
 * Class Phase
 * @package Lullabot\AMP\Validate
 *
 * This class does NOT exist in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The purpose of this class is for the Context class to define some constants to indicate whether its in a local or
 * global phase of hunting for errors. Local errors are those which you know something is instantly wrong. Global errors
 * are where you have to run through the whole document/whole html fragement before knowing something is wrong.
 */
class Phase
{
    const LOCAL_PHASE = 'LOCAL_PHASE';
    const GLOBAL_PHASE = 'GLOBAL_PHASE';
    const UNKNOWN_PHASE = 'UNKNOWN_PHASE';
}
