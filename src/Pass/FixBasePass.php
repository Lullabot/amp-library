<?php
namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;
use Lullabot\AMP\Warning;
use Lullabot\AMP\Spec\ValidatorRules;

abstract class FixBasePass
{
    /** @var DOMQuery */
    protected $q;
    /** @var array */
    protected $warnings = [];
    /** @var  ValidatorRules */
    protected $rules;
    /** @var array */
    protected $options;

    /**
     * FixBasePass constructor.
     * @param DOMQuery $q
     * @param ValidatorRules $rules
     * @param array $options
     */
    function __construct(DOMQuery $q, ValidatorRules $rules, $options = [])
    {
        $this->q = $q;
        $this->rules = $rules;
        $this->options = $options;
    }

    abstract function pass();

    protected function addWarning(Warning $w)
    {
        $this->warnings[] = $w;
    }

    protected function getSpecifications($tagname)
    {
        // @todo
    }

    /**
     * Rename $el to $tagname. Returns the renamed DOMElement.
     *
     * @param \DOMElement $el
     * @param $tagname
     * @return \DOMElement
     */
    protected function renameDomElement(\DOMElement $el, $tagname)
    {
        $new_el = $el->ownerDocument->createElement($tagname);

        // Renamed DOMElement should have the same children as original
        /** @var \DOMElement $child */
        foreach ($el->childNodes as $child) {
            $new_el->appendChild($child);
        }

        // Renamed DOMElement should have the same attributes as original
        /** @var \DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $new_el->setAttribute($attr->nodeName, $attr->nodeValue);
        }

        // Replace the old element with new element
        $el->parentNode->insertBefore($new_el, $el);
        $el->parentNode->removeChild($el);

        return $new_el;
    }
}
