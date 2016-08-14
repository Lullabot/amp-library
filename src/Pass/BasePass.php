<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Spec\AmpLayoutLayout;
use Lullabot\AMP\Utility\ParseUrl;
use Lullabot\AMP\Validate\GroupedValidationResult;
use Lullabot\AMP\Validate\ParsedTagSpec;
use Lullabot\AMP\Validate\ParsedValidatorRules;
use Lullabot\AMP\Validate\Scope;
use Lullabot\AMP\Validate\SValidationResult;
use QueryPath\DOMQuery;
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Validate\Context;

abstract class BasePass
{
    /** @var DOMQuery */
    protected $q;
    /** @var ActionTakenLine[] */
    protected $transformations = [];
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
    /** @var  GroupedValidationResult */
    protected $grouped_validation_result;

    /**
     * FixBasePass constructor.
     * @param DOMQuery $q
     * @param Context $context
     * @param SValidationResult $validation_result
     * @param ParsedValidatorRules $parsed_rules
     * @param array $options
     */
    function __construct(DOMQuery $q, Context $context, SValidationResult $validation_result, GroupedValidationResult $grouped_validation_result, ParsedValidatorRules $parsed_rules, $options = [])
    {
        $this->q = $q;
        $this->parsed_rules = $parsed_rules;
        $this->options = $options;
        $this->context = $context;
        $this->validation_result = $validation_result;
        $this->grouped_validation_result = $grouped_validation_result;
    }

    function getWarnings()
    {
        return $this->transformations;
    }

    abstract function pass();

    protected function addActionTaken(ActionTakenLine $w)
    {
        $this->transformations[] = $w;
    }

    /**
     * Provide some context in error messages.
     * @param \DOMElement $dom_el
     * @return string
     */
    protected function getContextString(\DOMElement $dom_el)
    {
        return $this->context->getContextString($dom_el);
    }

    /**
     * clone $el and then rename tag to $tagname. Returns the cloned DOMElement.
     *
     * @param \DOMElement $el
     * @param $tagname
     * @return \DOMElement
     */
    protected function cloneAndRenameDomElement(\DOMElement $el, $tagname)
    {
        $new_el = $el->ownerDocument->createElement($tagname);

        // Renamed DOMElement should have the same children as original
        /** @var \DOMElement $child */
        foreach ($el->childNodes as $child) {
            // @TODO must we cloneNode(true) ?
            $new_el->appendChild($child->cloneNode(true));
        }

        // Renamed DOMElement should have the same attributes as original
        /** @var \DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $new_el->setAttribute($attr->nodeName, $attr->nodeValue);
        }

        // Replace the old element with new element
        $el->parentNode->insertBefore($new_el, $el);

        return $new_el;
    }

    /**
     * Returns all attributes and attribute values on an dom element
     *
     * @param \DOMElement $el
     * @return string[]
     */
    protected function encounteredAttributes(\DOMElement $el)
    {
        return $this->context->encounteredAttributes($el);
    }

    /**
     * Manually adds a <script> tag for a custom component to the HTML <head>
     * @param string $component
     * @return bool
     */
    protected function addComponentJsToHead($component)
    {
        if ($this->options['scope'] != Scope::HTML_SCOPE || !isset(Context::$component_mappings[$component])) {
            return false;
        }

        $qp = $this->q->branch();
        $head = $qp->top()->find('head');
        if (empty($head->count())) {
            return false;
        }

        // Add new line to just make it look good in the html
        $new_script = $head->append('  <script></script>' . PHP_EOL)->lastChild();
        $new_script->attr('async', '');
        $new_script->attr('custom-element', $component);
        $new_script->attr('src', Context::$component_mappings[$component]);
        return true;
    }

    /**
     * Get reference to associated <script> tag, if any.
     *
     * @param DOMQuery $el
     * @param string $regex
     * @return DOMQuery|null
     */
    protected function getScriptTag(DOMQuery $el, $regex)
    {
        $script_tags = $el->nextAll('script');
        $found_script_tag = null;
        /** @var DOMQuery $script_tag */
        foreach ($script_tags as $script_tag) {
            if (!empty($script_tag) && preg_match($regex, $script_tag->attr('src'))) {
                $found_script_tag = $script_tag;
                break;
            }
        }

        return $found_script_tag;
    }

    /**
     * @param DOMQuery $el
     * @param DOMQuery $new_el
     * @param int $default_width
     * @param int $default_height
     * @param double $default_aspect_ratio
     * @return string
     */
    protected function setStandardAttributesFrom(DOMQuery $el, DOMQuery $new_el, $default_width, $default_height, $default_aspect_ratio)
    {
        // Preserve the data-*, width, height attributes only
        foreach ($el->attr() as $attr_name => $attr_value) {
            if (mb_strpos($attr_name, 'data-', 0, 'UTF-8') === 0) {
                $new_el->attr($attr_name, $attr_value);
            }
        }

        $height = (int) $el->attr('height');
        $width = (int) $el->attr('width');
        if (empty($height) && !empty($width)) {
            $height = (int)($width / $default_aspect_ratio);
        }

        if (!empty($height) && empty($width)) {
            $width = (int)($height * $default_aspect_ratio);
        }

        if (empty($height) && empty($width)) {
            $width = $default_width;
            $height = $default_height;
        }

        $new_el->attr('height', $height);
        $new_el->attr('width', $width);
    }

    /**
     * Get track/video/etc. id
     *
     * @param DOMQuery $el
     * @param string $regex
     * @param string $attr_name
     * @return bool|string
     */
    protected function getArtifactId(DOMQuery $el, $regex, $attr_name = 'src')
    {
        $href = $el->attr($attr_name);
        if (empty($href)) {
            return false;
        }

        $matches = [];
        if (preg_match($regex, $href, $matches)) {
            if (!empty($matches[1])) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * @param DOMQuery $el
     * @return array|bool
     */
    protected function getQueryArray(DOMQuery $el)
    {
        $href = $el->attr('src');
        if (empty($href)) {
            return false;
        }

        $query = ParseUrl::parse_url($href, PHP_URL_QUERY);
        if ($query === null) {
            return [];
        }

        $arr = [];
        parse_str($query, $arr);

        return $arr;
    }

    /**
     * @param \DOMElement $dom_el
     * @return int
     */
    public function getLineNo(\DOMElement $dom_el)
    {
        return $this->context->getLineNo($dom_el);
    }

    /**
     * @param DOMQuery $el
     * @param string $layout
     */
    function setLayoutIfNoLayout(DOMQuery $el, $layout = 'responsive')
    {
        $curr_layout = ParsedTagSpec::parseLayout($el->attr('layout'));

        if ($curr_layout === AmpLayoutLayout::UNKNOWN) {
            $el->attr('layout', $layout);
        }
    }
}
