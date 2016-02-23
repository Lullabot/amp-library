<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Validate\ParsedValidatorRules;
use Lullabot\AMP\Validate\SValidationResult;
use QueryPath\DOMQuery;
use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\Validate\Context;

abstract class BasePass
{
    /** @var DOMQuery */
    protected $q;
    /** @var array */
    protected $warnings = [];
    /** @var  ParsedValidatorRules */
    protected $parsed_rules;
    /** @var array */
    protected $options;
    /** @var array */
    protected $component_js = [];
    /** @var Context */
    protected $context;
    /** @var SValidationResult */
    protected $validation_result;

    protected static $component_mappings = [
        'amp-analytics' => 'https://cdn.ampproject.org/v0/amp-analytics-latest.js',
        'amp-anim' => 'https://cdn.ampproject.org/v0/amp-anim-latest.js',
        'amp-audio' => 'https://cdn.ampproject.org/v0/amp-audio-latest.js',
        'amp-brightcove' => 'https://cdn.ampproject.org/v0/amp-brightcove-latest.js',
        'amp-carousel' => 'https://cdn.ampproject.org/v0/amp-carousel-latest.js',
        'amp-dailymotion' => 'https://cdn.ampproject.org/v0/amp-dailymotion-latest.js',
        'amp-facebook' => 'https://cdn.ampproject.org/v0/amp-facebook-latest.js',
        'amp-fit-text' => 'https://cdn.ampproject.org/v0/amp-fit-text-latest.js',
        'amp-font' => 'https://cdn.ampproject.org/v0/amp-font-latest.js',
        'amp-iframe' => 'https://cdn.ampproject.org/v0/amp-iframe-latest.js',
        'amp-instagram' => 'https://cdn.ampproject.org/v0/amp-instagram-latest.js',
        'amp-install-serviceworker' => 'https://cdn.ampproject.org/v0/amp-install-serviceworker-latest.js',
        'amp-image-lightbox' => 'https://cdn.ampproject.org/v0/amp-image-lightbox-latest.js',
        'amp-lightbox' => 'https://cdn.ampproject.org/v0/amp-lightbox-latest.js',
        'amp-list' => 'https://cdn.ampproject.org/v0/amp-list-latest.js',
        'amp-pinterest' => 'https://cdn.ampproject.org/v0/amp-pintrest-latest.js',
        'amp-soundcloud' => 'https://cdn.ampproject.org/v0/amp-soundcloud-latest.js',
        'amp-twitter' => 'https://cdn.ampproject.org/v0/amp-twitter-latest.js',
        'amp-user-notification' => 'https://cdn.ampproject.org/v0/amp-user-notification-latest.js',
        'amp-vine' => 'https://cdn.ampproject.org/v0/amp-vine-latest.js',
        'amp-vimeo' => 'https://cdn.ampproject.org/v0/amp-vimeo-latest.js',
        'amp-youtube' => 'https://cdn.ampproject.org/v0/amp-youtube-latest.js',
        'template' => 'https://cdn.ampproject.org/v0/amp-mustache-latest.js'
    ];

    /**
     * FixBasePass constructor.
     * @param DOMQuery $q
     * @param Context $context
     * @param SValidationResult $validation_result
     * @param ParsedValidatorRules $parsed_rules
     * @param array $options
     */
    function __construct(DOMQuery $q, Context $context, SValidationResult $validation_result, ParsedValidatorRules $parsed_rules, $options = [])
    {
        $this->q = $q;
        $this->parsed_rules = $parsed_rules;
        $this->options = $options;
        $this->context = $context;
        $this->validation_result = $validation_result;
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

    protected function addActionTaken(ActionTakenLine $w)
    {
        $this->warnings[] = $w;
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
