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
    /** @var array */
    protected $component_js = [];

    protected static $component_mappings = [
        "amp-anim" => "https://cdn.ampproject.org/v0/amp-anim-0.1.js",
        "amp-audio" => "https://cdn.ampproject.org/v0/amp-audio-0.1.js",
        "amp-carousel" => "https://cdn.ampproject.org/v0/amp-carousel-0.1.js",
        "amp-fit-text" => "https://cdn.ampproject.org/v0/amp-fit-text-0.1.js",
        "amp-font" => "https://cdn.ampproject.org/v0/amp-font-0.1.js",
        "amp-iframe" => "https://cdn.ampproject.org/v0/amp-iframe-0.1.js",
        "amp-instagram" => "https://cdn.ampproject.org/v0/amp-instagram-0.1.js",
        "amp-image-lightbox" => "https://cdn.ampproject.org/v0/amp-image-lightbox-0.1.js",
        "amp-lightbox" => "https://cdn.ampproject.org/v0/amp-lightbox-0.1.js",
        "amp-twitter" => "https://cdn.ampproject.org/v0/amp-twitter-0.1.js",
        "amp-youtube" => "https://cdn.ampproject.org/v0/amp-youtube-0.1.js"
    ];

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

    function getWarnings()
    {
        return $this->warnings;
    }

    function getComponentJs()
    {
        return $this->component_js;
    }

    function addComponent($component_name)
    {
        if (isset(self::$component_mappings[$component_name])) {
            $this->component_js[$component_name] = self::$component_mappings[$component_name];
        }
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

    protected function encounteredAttributes(\DOMElement $el)
    {
        $encountered_attributes = [];
        /** @var \DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $encountered_attributes[$attr->nodeName] = $attr->nodeValue;
        }

        return $encountered_attributes;
    }

}
