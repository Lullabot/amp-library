<?php

namespace Lullabot\AMP\Validate;

/**
 * Class TagSpecDispatch
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the TagSpecDispatch class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 */
class TagSpecDispatch
{
    /** @var ParsedTagSpec[] */
    public $all_tag_specs = [];
    /** @var ParsedTagSpec[] */
    public $tag_specs_by_dispatch = [];
}