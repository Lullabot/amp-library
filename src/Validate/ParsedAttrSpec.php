<?php

use Lullabot\AMP\Spec\AttrSpec;

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
