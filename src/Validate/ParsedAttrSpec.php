<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\AttrSpec;

/**
 * Class ParsedAttrSpec
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedAttrSpec class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 */
class ParsedAttrSpec
{
    /** @var  AttrSpec */
    public $spec;
    // Set
    public $value_url_allowed_protocols = [];

    // @todo
    public function __construct(AttrSpec $attr_spec)
    {
        $this->spec = $attr_spec;
        // @todo
    }

    /**
     * @return AttrSpec
     */
    public function getSpec()
    {
        return $this->spec;
    }
}
