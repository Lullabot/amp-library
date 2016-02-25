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

use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Spec\ValidationErrorSeverity;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationError;

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
 * Another interesting features of our validator (compared to validator.js) is our ability to only show errors within
 * a portion of the document. Our validator is able to deal with validating with HTML fragments rather than whole
 * documents at a time, also. The Context class plays an important role in showing only those errors that are relevant.
 * See ignoreTagDueToScope(), ignoreErrorDueToPhase(), setAcceptableMandatoryParents(), get|setErrorScope(),
 * set|getPhase() (and the places where these a called in this class and the rest of the validator) to see how this is
 * implemented.
 *
 */
class Context
{
    /** @var \DOMElement */
    protected $dom_tag = null;
    /** @var \SplObjectStorage */
    protected $tagspecs_validated;
    protected $mandatory_alternatives_satisfied = []; // Set of strings
    protected $max_errors = -1;
    protected $parent_tag_name = '';
    protected $ancestor_tag_names = [];
    protected $phase = Phase::LOCAL_PHASE;
    protected $error_scope = Scope::HTML_SCOPE;
    protected $acceptable_mandatory_parents = [];
    /** @var \SplObjectStorage */
    protected $line_association;

    public function __construct($scope = Scope::BODY_SCOPE, $max_errors = -1)
    {
        $this->tagspecs_validated = new \SplObjectStorage();
        $this->max_errors = $max_errors;
        $this->error_scope = $scope;
        $this->line_association = new \SplObjectStorage();
        $this->setAcceptableMandatoryParents();
    }

    /**
     * Utility function
     * @throws \Exception
     */
    protected function setAcceptableMandatoryParents()
    {
        if ($this->error_scope == Scope::HTML_SCOPE) {
            $this->acceptable_mandatory_parents = ['body', 'head', 'html', '!doctype'];
        } else if ($this->error_scope == Scope::BODY_SCOPE) {
            $this->acceptable_mandatory_parents = ['body'];
        } else if ($this->error_scope == Scope::HEAD_SCOPE) {
            $this->acceptable_mandatory_parents = ['head'];
        } else {
            throw new \Exception("Invalid scope $this->error_scope");
        }
    }

    /**
     * @param Phase ::LOCAL_PHASE|Phase::GLOBAL_PHASE|Phase::UNKNOWN_PHASE $phase
     */
    public function setPhase($phase)
    {
        $this->phase = $phase;
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
        $this->setAcceptableMandatoryParents();
    }

    /**
     * @param \DOMElement $new_dom_tag
     */
    public function attachDomTag(\DOMElement $new_dom_tag)
    {
        $this->dom_tag = $new_dom_tag;
        $this->setParentTagName();
        $this->setAncestorTagNames();
    }

    /**
     * @return \DOMElement
     */
    public function getDomTag()
    {
        return $this->dom_tag;
    }

    /**
     * @return string[]
     */
    protected function setAncestorTagNames()
    {
        $ancestor_tag_names = [];
        $tag = $this->dom_tag;
        while (($tag = $tag->parentNode) && !empty($tag->tagName)) {
            $ancestor_tag_names[] = $tag->tagName;
        }
        $ancestor_tag_names[] = '!doctype';
        $this->ancestor_tag_names = $ancestor_tag_names;
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
            $this->parent_tag_name = $this->dom_tag->parentNode->tagName;
        }
    }

    /**
     * @return string
     */
    public function getParentTagName()
    {
        return $this->parent_tag_name;
    }

    /**
     * @param $code
     * @param array $params
     * @param string $spec_url
     * @param SValidationResult $validationResult
     * @param string $attr_name
     * @return bool
     */
    public function addError($code, array $params, $spec_url, SValidationResult $validationResult, $attr_name = '')
    {
        if (empty($spec_url)) {
            $spec_url = '';
        }

        // This is for cases in which we dynamically substitute one tag for another
        // The line number is not available so we try to get that from line association spobjectstorage map
        if (!empty($this->dom_tag) && isset($this->line_association[$this->dom_tag])) {
            $line = $this->line_association[$this->dom_tag];
        } else if (!empty($this->dom_tag)) {
            $line = $this->dom_tag->getLineNo();
        } else {
            $line = 1;
        }

        return $this->addErrorWithLine($line, $code, $params, $spec_url, $validationResult, $attr_name);
    }

    /**
     * @param \DOMElement $el
     * @param number $lineno
     */
    function addLineAssociation(\DOMElement $el, $lineno)
    {
        $this->line_association[$el] = $lineno;
    }

    /**
     * If the error pertains to a tag in a scope that is not relevant to us, then ignore it
     */
    public function ignoreErrorDueToPhase()
    {
        if ($this->phase == Phase::LOCAL_PHASE) {
            // $this->ancestor_tag_names only has meaning if we're in the the LOCAL_PHASE
            if (!in_array($this->error_scope, $this->ancestor_tag_names)) {
                return true;
            }
        }

        return false;
    }

    /**
     * If we want errors only in body, for instance, then ignore all head related issues and so forth
     *
     * @param ParsedTagSpec $parsed_tag_spec
     * @return bool
     */
    public function ignoreTagDueToScope(ParsedTagSpec $parsed_tag_spec)
    {
        $tagspec = $parsed_tag_spec->getSpec();
        if (!empty($tagspec->mandatory_parent) && !in_array($tagspec->mandatory_parent, $this->acceptable_mandatory_parents)) {
            return true;
        }

        return false;
    }

    /**
     * @param $line
     * @param $validation_error_code
     * @param array $params
     * @param $spec_url
     * @param SValidationResult $validation_result
     * @param string $attr_name
     * @return bool
     */
    public function addErrorWithLine($line, $validation_error_code, array $params, $spec_url, SValidationResult $validation_result, $attr_name = '')
    {
        $progress = $this->getProgress($validation_result);
        if ($progress['complete']) {
            assert($validation_result->status === ValidationResultStatus::FAIL, 'Early PASS exit without full verification');
            return false;
        }

        if ($this->ignoreErrorDueToPhase()) {
            return true; // pretend that we've added the error
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
            $error->phase = $this->phase;
            if ($this->phase == Phase::LOCAL_PHASE) {
                $error->dom_tag = $this->dom_tag;
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

    public static function severityFor($validation_error_code)
    {
        // @todo make more error codes less severe as we're going to be able to fix some of them
        if ($validation_error_code === ValidationErrorCode::DEPRECATED_TAG) {
            return ValidationErrorSeverity::WARNING;
        } else if ($validation_error_code == ValidationErrorCode::DEPRECATED_ATTR) {
            return ValidationErrorSeverity::WARNING;
        }

        return ValidationErrorSeverity::ERROR;
    }
}

