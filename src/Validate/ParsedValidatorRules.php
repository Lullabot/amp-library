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

use Lullabot\AMP\Pass\BasePass;
use Lullabot\AMP\Spec\AttrList;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Spec\ValidationRulesFactory;


/**
 * Class ParsedValidatorRules
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedValidatorRules class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/main/validator/validator.js )
 *
 */
class ParsedValidatorRules
{
    /** @var ValidatorRules */
    public $rules;
    /** @var string[] */
    public $format_by_code = [];
    /** @var TagSpecDispatch[] */
    protected $tag_dispatch_by_tag_name = [];
    /** @var \SplObjectStorage */ // key is a TagSpec, value is a ParsedTagSpec
    protected $all_parsed_specs_by_specs;

    /** @var ParsedTagSpec[] */
    protected $mandatory_tag_specs = [];

    /** @var ParsedValidatorRules|null */
    public static $parsed_validator_rules_singleton = null;

    /**
     * The ParsedValidatorRules object is expensive to create so we maintain a global singleton
     *
     * @return ParsedValidatorRules
     */
    public static function getSingletonParsedValidatorRules()
    {
        if (!empty(self::$parsed_validator_rules_singleton)) {
            return self::$parsed_validator_rules_singleton;
        } else {
            /** @var ValidatorRules $rules */
            $rules = ValidationRulesFactory::createValidationRules();
            self::$parsed_validator_rules_singleton = new self($rules);
            return self::$parsed_validator_rules_singleton;
        }
    }

    /**
     * Should only be used for testing when you need to adjust rules.
     *
     * @param \Lullabot\AMP\Spec\ValidatorRules $rules
     *
     * @return \Lullabot\AMP\Validate\ParsedValidatorRules
     */
    public static function createParsedValidatorRulesFromValidatorRules(ValidatorRules $rules) {
        return new self($rules);
    }

    /**
     * Note that this is deliberately protected
     *
     * ParsedValidatorRules constructor.
     * @param ValidatorRules $rules
     */
    protected function __construct(ValidatorRules $rules)
    {
        $this->rules = $rules;
        $this->all_parsed_specs_by_specs = new \SplObjectStorage();

        /** @var AttrList[] $attr_lists_by_name */
        $attr_lists_by_name = [];
        foreach ($this->rules->attr_lists as $attr_list_obj) {
            $attr_lists_by_name[$attr_list_obj->name] = $attr_list_obj;
        }

        $tagspec_by_detail_or_name = [];
        // This is a set
        $detail_or_names_to_track = [];
        /** @var TagSpec $tagspec */
        foreach ($this->rules->tags as $tagspec) {
            assert(empty($tagspec_by_detail_or_name[ParsedTagSpec::getTagSpecName($tagspec)]));
            $tagspec_by_detail_or_name[ParsedTagSpec::getTagSpecName($tagspec)] = $tagspec;

            if (!empty($tagspec->also_requires_tag)) {
                $detail_or_names_to_track[ParsedTagSpec::getTagSpecName($tagspec)] = 1;
            }

            /** @var string $require */
            foreach ($tagspec->also_requires_tag as $require) {
                $detail_or_names_to_track[$require] = 1;
            }
        }

        /** @var TagSpec $tag_spec */
        foreach ($this->rules->tags as $tagspec) {
            /** @var ParsedTagSpec $parsed_tag_spec */
            $parsed_tag_spec = new ParsedTagSpec($this->rules->template_spec_url, $attr_lists_by_name, $tagspec_by_detail_or_name, ParsedTagSpec::shouldRecordTagspecValidatedTest($tagspec, $detail_or_names_to_track), $tagspec);
            assert(!empty($tagspec->tag_name));
            $this->all_parsed_specs_by_specs[$tagspec] = $parsed_tag_spec;

            if (!isset($this->tag_dispatch_by_tag_name[$tagspec->tag_name])) {
                $this->tag_dispatch_by_tag_name[$tagspec->tag_name] = new TagSpecDispatch();
            }

            /** @var TagSpecDispatch $tagname_dispatch */
            $tagname_dispatch = $this->tag_dispatch_by_tag_name[$tagspec->tag_name];
            if ($parsed_tag_spec->hasDispatchKey()) {
                $tagname_dispatch->registerDispatchKey($parsed_tag_spec->getDispatchKey(), $parsed_tag_spec);
            } else {
                $tagname_dispatch->registerTagSpec($parsed_tag_spec);
            }

            if ($tagspec->mandatory) {
                $this->mandatory_tag_specs[] = $parsed_tag_spec;
            }
        }

        foreach ($this->rules->error_formats as $error_format) {
            assert(!empty($error_format));
            $this->format_by_code[$error_format->code] = $error_format->format;
        }
    }

    public function getFormatByCode()
    {
        return $this->format_by_code;
    }

    /**
     * @param Context $context
     * @param string $tag_name
     * @param array $encountered_attributes
     * @param SValidationResult $validation_result
     */
    public function validateTag(Context $context, $tag_name, array $encountered_attributes, SValidationResult $validation_result)
    {
        /** @var TagSpecDispatch $tag_spec_dispatch */
        $tag_spec_dispatch = isset($this->tag_dispatch_by_tag_name[$tag_name]) ? $this->tag_dispatch_by_tag_name[$tag_name] : null;
        if (empty($tag_spec_dispatch)) {
            $context->addError(ValidationErrorCode::DISALLOWED_TAG, [$tag_name], '', $validation_result);
            return;
        }

        $result_for_best_attempt = new SValidationResult();
        $result_for_best_attempt->status = ValidationResultStatus::FAIL;

        // Try to validate against a specification if we're able to dispatch based on attribute key, value and optionally, parent name
        if ($tag_spec_dispatch->hasDispatchKeys()) {
            foreach ($encountered_attributes as $attr_name => $attr_value) {
                if (empty($attr_value)) {
                    $attr_value = '';
                }
                $attr_name = mb_strtolower($attr_name, 'UTF-8');
                $attr_value = mb_strtolower($attr_value, 'UTF-8');

                /** @var ParsedTagSpec|null $match_spec */
                $match_spec = $tag_spec_dispatch->matchingDispatchKey($attr_name, $attr_value, $context->getParentTagName());
                if (!empty($match_spec)) {
                    $this->validateTagAgainstSpec($match_spec, $context, $encountered_attributes, $result_for_best_attempt);
                    $validation_result->mergeFrom($result_for_best_attempt);
                    return;
                }
            }

            if (!$tag_spec_dispatch->hasTagSpecs()) {
                $context->addError(ValidationErrorCode::GENERAL_DISALLOWED_TAG, [$tag_name], '', $validation_result);
                return;
            }
        }

        // we were not able to dispatch based on a dispatch key, try all matching parsed specfications for that tag name
        foreach ($tag_spec_dispatch->allTagSpecs() as $parsed_spec) {
            $this->validateTagAgainstSpec($parsed_spec, $context, $encountered_attributes, $result_for_best_attempt);
            // If we succeeded
            if ($result_for_best_attempt->status !== ValidationResultStatus::FAIL) {
                break;
            }
        }

        $validation_result->mergeFrom($result_for_best_attempt);
    }

    /**
     * @param Context $context
     * @param SValidationResult $result
     */
    public function validateTagOnExit(Context $context, SValidationResult $result)
    {
        /** @var CdataMatcher $cdata_matcher */
        $cdata_matcher = $context->getCdataMatcher();
        $cdata_matcher->match($context->getDomTag()->textContent, $context, $result);
        /** @var ChildTagMatcher $child_tag_matcher */
        $child_tag_matcher = $context->getChildTagMatcher();
        $child_tag_matcher->matchChildTagName($context, $result);
    }

    /**
     * @param ParsedTagSpec $parsed_spec
     * @param Context $context
     * @param array $encountered_attributes
     * @param SValidationResult $result_for_best_attempt
     */
    public function validateTagAgainstSpec(ParsedTagSpec $parsed_spec, Context $context, array $encountered_attributes, SValidationResult $result_for_best_attempt)
    {
        // We should be failing so far, otherwise why are we being called?
        assert($result_for_best_attempt->status === ValidationResultStatus::FAIL);

        $result_for_attempt = new SValidationResult();
        $result_for_attempt->status = ValidationResultStatus::UNKNOWN;
        $parsed_spec->validateAttributes($context, $encountered_attributes, $result_for_attempt);
        $parsed_spec->validateParentTag($context, $result_for_attempt);
        $parsed_spec->validateAncestorTags($context, $result_for_attempt);

        // This is an important piece of code. Essentially, if the only errors you got were highly specific then that
        // means that you choose the correct tagspec to validate against. (This becomes useful when we don't have dispatch
        // keys. e.g. source tag). Since the errors were highly specific, make this your "best result"
        if ($result_for_attempt->status === ValidationResultStatus::FAIL) {
            if (empty($result_for_best_attempt->errors)) {
                $result_for_best_attempt->status = $result_for_attempt->status;
                $result_for_best_attempt->errors = $result_for_attempt->errors;
                return;
            }

            // Least errors is better
            if (count($result_for_best_attempt->errors) > count($result_for_attempt->errors)) {
                $result_for_best_attempt->status = $result_for_attempt->status;
                $result_for_best_attempt->errors = $result_for_attempt->errors;
                return;
            }

            // Skip if more errors
            if (count($result_for_best_attempt->errors) < count($result_for_attempt->errors)) {
                return;
            }

            // If we're here then it means that number of errors is the same
            // Choose the case with most specific errors
            if (SValidationResult::maxSpecificity($result_for_attempt) > SValidationResult::maxSpecificity($result_for_best_attempt)) {
                $result_for_best_attempt->status = $result_for_attempt->status;
                $result_for_best_attempt->errors = $result_for_attempt->errors;
            }

            return;
        }

        // We succeeded as we haven't exited so far!
        $result_for_best_attempt->status = $result_for_attempt->status;
        $result_for_best_attempt->errors = $result_for_attempt->errors;

        /** @var TagSpec $spec */
        $spec = $parsed_spec->getSpec();
        if (!empty($spec->deprecation)) {
            $context->addError(ValidationErrorCode::DEPRECATED_TAG, [ParsedTagSpec::getTagSpecName($spec), $spec->deprecation], $spec->deprecation_url, $result_for_best_attempt);
            // don't return as its just a warning, see Context::severityFor()
        }

        if ($parsed_spec->shouldRecordTagspecValidated()) {
            $is_unique = $context->recordTagspecValidated($parsed_spec);
            if ($parsed_spec->getSpec()->unique && $is_unique !== true) {
                /** @var ParsedTagSpec $parsed_spec */
                $spec = $parsed_spec->getSpec();
                $context->addError(ValidationErrorCode::DUPLICATE_UNIQUE_TAG, [ParsedTagSpec::getTagSpecName($spec)], $spec->spec_url, $result_for_best_attempt);
                return;
            }
        }

        if (!empty($parsed_spec->getSpec()->mandatory_alternatives)) {
            $satisfied = $parsed_spec->getSpec()->mandatory_alternatives;
            $context->recordMandatoryAlternativesSatisfied($satisfied);
        }

        $context->setCdataMatcher(new CdataMatcher($parsed_spec->getSpec()));
        $context->setChildTagMatcher(new ChildTagMatcher($parsed_spec->getSpec()));
    }

    /**
     * @param Context $context
     * @param SValidationResult $validation_result
     */
    public function maybeEmitMandatoryTagValidationErrors(Context $context, SValidationResult $validation_result)
    {
        if ($context->skipGlobalValidationErrors()) {
            return;
        }

        /** @var ParsedTagSpec $parsed_tag_spec */
        foreach ($this->mandatory_tag_specs as $parsed_tag_spec) {
            $tagspec = $parsed_tag_spec->getSpec();
            if (!$context->getTagspecsValidated()->contains($parsed_tag_spec)) {
                if (!$context->addError(ValidationErrorCode::MANDATORY_TAG_MISSING,
                    [ParsedTagSpec::getTagSpecName($tagspec)], $tagspec->spec_url, $validation_result)
                ) {
                    return;
                };
            }
        }
    }

    /**
     * @param Context $context
     * @param SValidationResult $validation_result
     * @param BasePass $base_pass
     */
    public function maybeEmitAlsoRequiresValidationErrors(Context $context, SValidationResult $validation_result, BasePass $base_pass)
    {
        /** @var ParsedTagSpec $parsed_tag_spec */
        foreach ($context->getTagspecsValidated() as $parsed_tag_spec) {
            /** @var TagSpec $tagspec_require */
            foreach ($parsed_tag_spec->getAlsoRequiresTagspec() as $tagspec_require) {
                /** @var ParsedTagSpec $parsed_tag_spec_require */
                $parsed_tag_spec_require = $this->all_parsed_specs_by_specs[$tagspec_require];
                assert(!empty($parsed_tag_spec_require));
                if (preg_match('/(*UTF8)extension \.js script$/i', $tagspec_require->spec_name)) {
                    $context->addComponent($parsed_tag_spec->getSpec()->tag_name);
                }

                // Note that this comes after the addComponent call
                // We don't exit as there might be other components
                if ($context->skipGlobalValidationErrors()) {
                    continue;
                }

                if (!$context->getTagspecsValidated()->contains($parsed_tag_spec_require)) {
                    if (!$context->addError(ValidationErrorCode::TAG_REQUIRED_BY_MISSING,
                        [ParsedTagSpec::getTagSpecName($tagspec_require), ParsedTagSpec::getTagSpecName($parsed_tag_spec->getSpec())],
                        $tagspec_require->spec_url, $validation_result)
                    ) {
                        return;
                    }
                }
            }
        }
    }

    public function maybeEmitMandatoryAlternativesSatisfiedErrors(Context $context, SValidationResult $validation_result)
    {
        if ($context->skipGlobalValidationErrors()) {
            return;
        }

        /** @var  $satisfied_alternatives */
        $satisfied_alternatives = $context->getMandatoryAlternativesSatisfied();
        $missing_mandatory_alternatives = [];
        /** @var TagSpec $tagspec */
        // Remember that we're iterating through SplObjectStorage here
        foreach ($this->all_parsed_specs_by_specs as $tagspec) {
            /** @var ParsedTagSpec $parsed_tag_spec */
            $parsed_tag_spec = $this->all_parsed_specs_by_specs[$tagspec];
            if (!empty($tagspec->mandatory_alternatives)) {
                $alternative = $tagspec->mandatory_alternatives;
                if (!isset($satisfied_alternatives[$alternative])) {
                    $missing_mandatory_alternatives[$alternative] = $parsed_tag_spec->getSpec()->spec_url;
                }
            }
        }

        foreach ($missing_mandatory_alternatives as $missing_tag_detail => $missing_spec_url) {
            if (!$context->addError(ValidationErrorCode::MANDATORY_TAG_MISSING, [$missing_tag_detail], $missing_spec_url, $validation_result)) {
                return;
            }
        }
    }

    public function maybeEmitGlobalTagValidationErrors(Context $context, SValidationResult $validation_result, BasePass $base_pass)
    {
        $context->setPhase(Phase::GLOBAL_PHASE);
        if ($context->getProgress($validation_result)['complete']) {
            return;
        }
        $this->maybeEmitMandatoryTagValidationErrors($context, $validation_result);
        if ($context->getProgress($validation_result)['complete']) {
            return;
        }
        $this->maybeEmitAlsoRequiresValidationErrors($context, $validation_result, $base_pass);
        if ($context->getProgress($validation_result)['complete']) {
            return;
        }
        $this->maybeEmitMandatoryAlternativesSatisfiedErrors($context, $validation_result);
    }

    /**
     * Call this at the end of the validation
     * @param SValidationResult $validation_result
     */
    public function endValidation(SValidationResult $validation_result)
    {
        if ($validation_result->status == ValidationResultStatus::UNKNOWN) {
            $validation_result->status = ValidationResultStatus::PASS;
        }
    }
}
