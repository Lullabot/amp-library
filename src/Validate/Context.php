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

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Spec\ValidationErrorSeverity;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\AMP;

/**
 * Class Context
 * @package Lullabot\AMP\Validate
 *
 * This class is a PHP port of the Context class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The static method severityFor() is a normal top-level function in validator.js but has been incorporated
 * into this class for convenience.
 *
 * The main difference between the PHP Port and the js version is that the Context class here will be working with a DOM
 * style parser (PHP dom extension) while it was working with an event based/sax style in validator.js
 *
 * Another interesting feature of our validator (compared to validator.js) is our ability to only show errors within
 * a portion of the document. Our validator is able to deal with validating with HTML fragments rather than whole
 * documents at a time, also. The Context class plays an important role in showing only those errors that are relevant.
 *
 */
class Context
{
    /** @var \DOMElement */
    protected $dom_tag = null;
    /** @var \SplObjectStorage */
    protected $tagspecs_validated;
    protected $mandatory_alternatives_satisfied = []; // Set of strings
    /** @var int */
    protected $max_errors = -1;
    /** @var string */
    protected $parent_tag_name = '';
    /** @var string[] */
    protected $ancestor_tag_names = [];
    /** @var string[] */
    protected $child_tag_names = [];
    protected $phase = Phase::PRE_LOCAL_PHASE;
    protected $error_scope = Scope::HTML_SCOPE;
    /** @var \SplObjectStorage */
    protected $line_association;
    /** @var int */
    protected $num_tags_processed = 0;
    /** @var array */
    protected $stats_data = [];
    /** @var array */
    protected $options = [];
    /** @var CdataMatcher */
    protected $cdata_matcher = null;
    /** @var ChildTagMatcher */
    protected $child_tag_matcher = null;
    /** @var string[] */
    protected $component_js = [];

    public static $component_mappings = [
        'amp-analytics' => 'https://cdn.ampproject.org/v0/amp-analytics-0.1.js',
        'amp-anim' => 'https://cdn.ampproject.org/v0/amp-anim-0.1.js',
        'amp-audio' => 'https://cdn.ampproject.org/v0/amp-audio-0.1.js',
        'amp-brightcove' => 'https://cdn.ampproject.org/v0/amp-brightcove-0.1.js',
        'amp-carousel' => 'https://cdn.ampproject.org/v0/amp-carousel-0.1.js',
        'amp-dailymotion' => 'https://cdn.ampproject.org/v0/amp-dailymotion-0.1.js',
        'amp-facebook' => 'https://cdn.ampproject.org/v0/amp-facebook-0.1.js',
        'amp-fit-text' => 'https://cdn.ampproject.org/v0/amp-fit-text-0.1.js',
        'amp-font' => 'https://cdn.ampproject.org/v0/amp-font-0.1.js',
        'amp-iframe' => 'https://cdn.ampproject.org/v0/amp-iframe-0.1.js',
        'amp-instagram' => 'https://cdn.ampproject.org/v0/amp-instagram-0.1.js',
        'amp-install-serviceworker' => 'https://cdn.ampproject.org/v0/amp-install-serviceworker-0.1.js',
        'amp-image-lightbox' => 'https://cdn.ampproject.org/v0/amp-image-lightbox-0.1.js',
        'amp-lightbox' => 'https://cdn.ampproject.org/v0/amp-lightbox-0.1.js',
        'amp-list' => 'https://cdn.ampproject.org/v0/amp-list-0.1.js',
        'amp-pinterest' => 'https://cdn.ampproject.org/v0/amp-pinterest-0.1.js',
        'amp-soundcloud' => 'https://cdn.ampproject.org/v0/amp-soundcloud-0.1.js',
        'amp-twitter' => 'https://cdn.ampproject.org/v0/amp-twitter-0.1.js',
        'amp-user-notification' => 'https://cdn.ampproject.org/v0/amp-user-notification-0.1.js',
        'amp-vine' => 'https://cdn.ampproject.org/v0/amp-vine-0.1.js',
        'amp-vimeo' => 'https://cdn.ampproject.org/v0/amp-vimeo-0.1.js',
        'amp-youtube' => 'https://cdn.ampproject.org/v0/amp-youtube-0.1.js',
        'template' => 'https://cdn.ampproject.org/v0/amp-mustache-0.1.js'
    ];

    /**
     * Context constructor.
     * @param string $scope
     * @param array $options
     * @param int $max_errors
     */
    public function __construct($scope = Scope::BODY_SCOPE, $options = [], $max_errors = -1)
    {
        $this->tagspecs_validated = new \SplObjectStorage();
        $this->max_errors = $max_errors;
        $this->error_scope = $scope;
        $this->line_association = new \SplObjectStorage();
        $this->options = $options;
        $this->cdata_matcher = new CdataMatcher(new TagSpec());
        $this->child_tag_matcher = new ChildTagMatcher(new TagSpec());
    }

    public function getOptions() {
        return $this->options;
    }

    /**
     * @param CdataMatcher $matcher
     */
    public function setCdataMatcher(CdataMatcher $matcher)
    {
        $this->cdata_matcher = $matcher;
    }

    /**
     * @return CdataMatcher
     */
    public function getCdataMatcher()
    {
        return $this->cdata_matcher;
    }

    /**
     * @param ChildTagMatcher $matcher
     */
    public function setChildTagMatcher(ChildTagMatcher $matcher)
    {
        $this->child_tag_matcher = $matcher;
    }

    /**
     * @return ChildTagMatcher
     */
    public function getChildTagMatcher()
    {
        return $this->child_tag_matcher;
    }

    /**
     * @return int
     */
    public function getNumTagsProcessed()
    {
        return $this->num_tags_processed;
    }

    public function setNumTagsProcessed($num_tags_processed)
    {
        $this->num_tags_processed = $num_tags_processed;
    }

    /**
     * @return array
     */
    public function getStatsData()
    {
        return $this->stats_data;
    }

    public function setStatsData(array $stats_data)
    {
        $this->stats_data = $stats_data;
    }

    /**
     * @param Phase ::LOCAL_PHASE|Phase::GLOBAL_PHASE|Phase::UNKNOWN_PHASE $phase
     */
    public function setPhase($phase)
    {
        $this->phase = $phase;
        // Clear any stored dom tag if changing the phase
        if ($this->phase !== $phase) {
            $this->dom_tag = null;
        }
    }

    public function getPhase()
    {
        return $this->phase;
    }

    public function getErrorScope()
    {
        return $this->error_scope;
    }

    /**
     * @param Scope ::HTML_SCOPE|Scope::HEAD_SCOPE|Scope::BODY_SCOPE $scope
     */
    public function setErrorScope($scope)
    {
        $this->error_scope = $scope;
    }

    /**
     * @param \DOMElement $new_dom_tag
     */
    public function attachDomTag(\DOMElement $new_dom_tag)
    {
        $this->dom_tag = $new_dom_tag;
        $this->setParentTagName();
        $this->setAncestorTagNames();
        $this->setChildTagNames();
    }

    /**
     * Performs some cleanup
     */
    public function detachDomTag()
    {
        // Remove the embedded line number; we won't need this anymore
        $this->dom_tag->removeAttribute(AMP::AMP_LINENUM_ATTRIBUTE);
        $this->dom_tag = null;
        $this->cdata_matcher = new CdataMatcher(new TagSpec());
        $this->child_tag_matcher = new ChildTagMatcher(new TagSpec());
    }

    /**
     * @return \DOMElement
     */
    public function getDomTag()
    {
        return $this->dom_tag;
    }

    protected function setAncestorTagNames()
    {
        $ancestor_tag_names = [];
        $tag = $this->dom_tag;
        while (($tag = $tag->parentNode) && !empty($tag->tagName)) {
            $ancestor_tag_names[] = mb_strtolower($tag->tagName, 'UTF-8');
        }
        $ancestor_tag_names[] = '!doctype';
        $this->ancestor_tag_names = $ancestor_tag_names;
    }

    protected function setChildTagNames()
    {
        $this->child_tag_names = [];
        /** @var \DOMNode $child_node */
        foreach ($this->dom_tag->childNodes as $child_node) {
            if ($child_node->nodeType == XML_ELEMENT_NODE) {
                /** @var \DOMElement $child_node */
                $tagname = mb_strtolower($child_node->tagName, 'UTF-8');
                $this->child_tag_names[] = $tagname;
            }
        }
    }

    /**
     * @return string[]
     */
    public function getAncestorTagNames()
    {
        return $this->ancestor_tag_names;
    }

    /**
     * @return string
     */
    protected function setParentTagName()
    {
        if (empty($this->dom_tag->parentNode->tagName)) {
            $this->parent_tag_name = '!doctype';
        } else {
            $this->parent_tag_name = mb_strtolower($this->dom_tag->parentNode->tagName, 'UTF-8');
        }
    }

    /**
     * @return string
     */
    public function getParentTagName()
    {
        return $this->parent_tag_name;
    }

    public function getChildTagNames()
    {
        return $this->child_tag_names;
    }

    /**
     * @param \DOMElement|null $dom_el
     * @return int
     */
    public function getLineNo(\DOMElement $dom_el = null)
    {
        if (empty($dom_el)) {
            $dom_el = $this->dom_tag;
        }

        if (empty($this->options['use_html5_parser'])) {
            return $dom_el->getLineNo();
        } else {
            $line_no = $dom_el->getAttribute(AMP::AMP_LINENUM_ATTRIBUTE);
            if (is_numeric($line_no)) {
                return (int)$line_no;
            } else {
                return 0;
            }
        }
    }

    /**
     * @param $code
     * @param array $params
     * @param string $spec_url
     * @param SValidationResult $validationResult
     * @param string $attr_name
     * @param string $segment
     * @param int $line_delta
     * @return bool
     */
    public function addError($code, array $params, $spec_url, SValidationResult $validationResult, $attr_name = '', $segment = '', $line_override = 0)
    {
        if (empty($spec_url)) {
            $spec_url = '';
        }

        // This is for cases in which we dynamically substitute one tag for another
        // The line number is not available so we try to get that from line association spobjectstorage map
        if (!empty($this->dom_tag) && isset($this->line_association[$this->dom_tag])) {
            $line = $this->line_association[$this->dom_tag];
        } else if (!empty($this->dom_tag)) {
            $line = $this->getLineNo($this->dom_tag);
        } else {
            $line = PHP_INT_MAX;
        }

        if (!empty($line_override)) {
            $line = $line_override;
        }
        return $this->addErrorWithLine($line, $code, $params, $spec_url, $validationResult, $attr_name, $segment);
    }

    /**
     * @param \DOMElement $el
     * @param number $lineno
     */
    function addLineAssociation(\DOMElement $el, $lineno)
    {
        $this->line_association[$el] = $lineno;
    }

    public function skipGlobalValidationErrors()
    {
        return ($this->error_scope === Scope::BODY_SCOPE);
    }

    /**
     * Provide some context in error messages.
     * The same method exists in class BasePass
     *
     * @param \DOMElement $dom_el
     * @return string
     */
    public function getContextString(\DOMElement $dom_el)
    {
        if (empty($dom_el)) {
            return '';
        }

        /** @var string[] $attributes */
        $attributes = $this->encounteredAttributes($dom_el);
        $tagname = mb_strtolower($dom_el->tagName, 'UTF-8');
        $context_string = "<$tagname";
        foreach ($attributes as $attr_name => $attr_value) {
            // Skip embedded line numbers
            if ($attr_name == AMP::AMP_LINENUM_ATTRIBUTE) {
                continue;
            }
            $context_string .= " $attr_name";
            // Skip empty attribute values
            if (!empty($attr_value)) {
                $context_string .= '="' . $attr_value . '"';
            }
        }
        $context_string .= '>';

        // Truncate long strings
        if (mb_strlen($context_string) > 200) {
            $context_string = mb_substr($context_string, 0, 200) . "...";
        }
        return $context_string;
    }

    /**
     * Returns all attributes and attribute values on an dom element
     * The same method exists in class BasePass
     *
     * @param \DOMElement $el
     * @return string[]
     */
    public function encounteredAttributes(\DOMElement $el)
    {
        $encountered_attributes = [];
        /** @var \DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $encountered_attributes[$attr->nodeName] = $attr->nodeValue;
        }

        return $encountered_attributes;
    }

    /**
     * If the error pertains to a tag in a scope that is not relevant to us, then ignore it
     */
    public function ignoreError()
    {
        // We never ignore anything in HTML Scope
        if ($this->error_scope == Scope::HTML_SCOPE) {
            return false;
        }

        if ($this->phase == Phase::LOCAL_PHASE) {
            // Note: $this->ancestor_tag_names only has meaning if we're in the the LOCAL_PHASE
            if (!in_array($this->error_scope, $this->ancestor_tag_names)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int|string $line
     * @param string $validation_error_code
     * @param array $params
     * @param $spec_url
     * @param SValidationResult $validation_result
     * @param string $attr_name
     * @return bool
     */
    public function addErrorWithLine($line, $validation_error_code, array $params, $spec_url, SValidationResult $validation_result, $attr_name = '', $segment = '')
    {
        // We currently don't issue this error as we're only looking at DOMElements
        if ($validation_error_code == ValidationErrorCode::MANDATORY_TAG_MISSING && isset($params[0]) && $params[0] == 'html doctype') {
            return true;
        }

        if ($this->ignoreError()) {
            return true; // pretend we added it
        }

        $progress = $this->getProgress($validation_result);
        if ($progress['complete']) {
            assert($validation_result->status === ValidationResultStatus::FAIL, 'Early PASS exit without full verification');
            return false;
        }

        $severity = self::severityFor($validation_error_code);
        if ($severity !== ValidationErrorSeverity::WARNING) {
            $validation_result->status = ValidationResultStatus::FAIL;
        }

        if ($progress['wants_more_errors']) {
            $error = new SValidationError();
            $error->severity = $severity;
            $error->code = $validation_error_code;
            $error->params = $params;
            $error->line = $line;
            // dont know the column number unfortunately
            $error->spec_url = $spec_url;
            // for more context
            $error->attr_name = $attr_name;
            // property value pairs within an attribute
            $error->segment = $segment;
            $error->phase = $this->phase;
            if ($this->phase == Phase::LOCAL_PHASE) {
                $error->dom_tag = $this->dom_tag;
                $error->context_string = $this->getContextString($this->dom_tag);
            } else {
                $error->context_string = AMP::AMP_GLOBAL_WARNING;
            }

            assert(isset($validation_result->errors));
            $validation_result->errors[] = $error;
        }

        return true;
    }

    /**
     * @param ParsedTagSpec $parsed_tag_spec
     * @return bool
     */
    public function recordTagspecValidated(ParsedTagSpec $parsed_tag_spec)
    {
        $duplicate = $this->tagspecs_validated->contains($parsed_tag_spec);
        if (!$duplicate) {
            $this->tagspecs_validated->attach($parsed_tag_spec);
        }
        return !$duplicate;
    }

    public function getMandatoryAlternativesSatisfied()
    {
        return $this->mandatory_alternatives_satisfied;
    }

    /**
     * @param string $satisfied
     */
    public function recordMandatoryAlternativesSatisfied($satisfied)
    {
        // Treat as a Set
        $this->mandatory_alternatives_satisfied[$satisfied] = 1;
    }

    /**
     * @param SValidationResult $validation_result
     * @return array
     */
    public function getProgress(SValidationResult $validation_result)
    {
        if ($this->max_errors === -1) {
            return ['complete' => false, 'wants_more_errors' => true];
        }

        if ($this->max_errors === 0) {
            return ['complete' => $validation_result->status === ValidationResultStatus::FAIL, 'wants_more_errors' => false];
        }

        $wants_more_errors = count($validation_result->errors) < $this->max_errors;
        return ['complete' => !$wants_more_errors, 'wants_more_errors' => $wants_more_errors];
    }

    /**
     * @return \SplObjectStorage
     */
    public function getTagspecsValidated()
    {
        return $this->tagspecs_validated;
    }

    /**
     * @param SValidationError $validation_error_code
     * @return string
     */
    public static function severityFor($validation_error_code)
    {
        if ($validation_error_code === ValidationErrorCode::DEPRECATED_TAG) {
            return ValidationErrorSeverity::WARNING;
        } else if ($validation_error_code == ValidationErrorCode::DEPRECATED_ATTR) {
            return ValidationErrorSeverity::WARNING;
        }

        return ValidationErrorSeverity::ERROR;
    }

    /**
     * @return bool
     */
    public function hasTemplateAncestor()
    {
        return in_array('template', $this->ancestor_tag_names);
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

}

