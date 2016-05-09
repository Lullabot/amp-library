<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\AttrTriggerSpec;

/**
 * Class ParsedAttrTriggerSpec
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedAttrTriggerSpec class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 */
class ParsedAttrTriggerSpec
{
    /** @var AttrTriggerSpec */
    protected $spec;
    /** @var string */
    protected $attr_name;
    /** @var string|null */
    protected $if_value_regex = null;

    public function __construct(AttrSpec $attr_spec)
    {
        $this->spec = $attr_spec->trigger;
        assert(!empty($attr_spec->name));
        $this->attr_name = $attr_spec->name;

        if (!empty($this->spec) && !empty($this->spec->if_value_regex)) {
            $this->if_value_regex = "&(*UTF8)$this->spec->if_value_regex&i";
        }
    }

    /**
     * @return boolean
     */
    public function hasIfValueRegex()
    {
        return $this->if_value_regex !== null;
    }

    /**
     * @return null|string
     */
    public function getIfValueRegex()
    {
        return $this->if_value_regex;
    }

    /**
     * @return null|string
     */
    public function getAttrName()
    {
        return $this->attr_name;
    }

    /**
     * @return AttrTriggerSpec|null
     */
    public function getSpec()
    {
        return $this->spec;
    }
}
