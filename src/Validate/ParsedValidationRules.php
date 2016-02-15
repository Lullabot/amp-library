<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\AttrList;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Spec\ErrorFormat;

class ParsedValidatorRules
{
    /** @var ValidatorRules */
    public $rules;
    /** @var ErrorFormat[] */
    public $format_by_code = [];
    /** @var TagSpecDispatch[] */
    protected $tag_dispatch_by_tag_name = [];
    /** @var \SplObjectStorage */ // key is a TagSpec, value is a ParsedTagSpec
    protected $all_parsed_specs_by_specs;

    /** @var ParsedTagSpec[] */
    protected $mandatory_tag_specs = [];

    public function __construct(ValidatorRules $rules)
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
            assert(empty($tagspec_by_detail_or_name[getDetailOrName($tagspec)]));
            $tagspec_by_detail_or_name[getDetailOrName($tagspec)] = $tagspec;

            if (!empty($tagspec->also_requires)) {
                $detail_or_names_to_track[getDetailOrName($tagspec)] = 1;
            }

            foreach ($tagspec->also_requires as $require) {
                $detail_or_names_to_track[$require] = 1;
            }
        }

        /** @var TagSpec $tag_spec */
        foreach ($this->rules->tags as $tagspec) {
            /** @var ParsedTagSpec $parsed_tag_spec */
            $parsed_tag_spec = new ParsedTagSpec($attr_lists_by_name, $tagspec_by_detail_or_name,
                shouldRecordTagspecValidated($tagspec, $detail_or_names_to_track), $tagspec);
            assert(!empty($tagspec->name));
            $this->all_parsed_tag_specs[$tagspec] = $parsed_tag_spec;

            if (!isset($this->tag_dispatch_by_tag_name[$tagspec->name])) {
                $this->tag_dispatch_by_tag_name[$tagspec->name] = new TagSpecDispatch();
            }

            /** @var TagSpecDispatch $tagname_dispatch */
            $tagname_dispatch = $this->tag_dispatch_by_tag_name[$tagspec->name];
            if ($parsed_tag_spec->hasDispatchKey()) {
                $tagname_dispatch->tag_specs_by_dispatch[$parsed_tag_spec->getDispatchKey()] = $parsed_tag_spec;
            }

            $tagname_dispatch->all_tag_specs[] = $parsed_tag_spec;

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
     * @param IValidationResult $validationResult
     */
    public function validateTag(Context $context, $tag_name, array $encountered_attributes, IValidationResult $validationResult)
    {
        /** @var TagSpecDispatch $tag_spec_dispatch */
        $tag_spec_dispatch = $this->tag_dispatch_by_tag_name[$tag_name];
        if (empty($tag_spec_dispatch)) {
            $context->addError(ValidationErrorCode::DISALLOWED_TAG, [$tag_name], '', $validationResult);
            return;
        }

        $result_for_best_attempt = new IValidationResult();
        $result_for_best_attempt->status = ValidationResultStatus::FAIL;

        // Try to validate against a specfication if we're able to dipatch based on attribute key and value
        if (!empty($tag_spec_dispatch->tag_specs_by_dispatch)) {
            foreach ($encountered_attributes as $attr_name => $attr_value) {
                if (empty($attr_value)) {
                    $attr_value = '';
                }
                $attr_name = mb_strtolower($attr_name);
                /** @var ParsedTagSpec $match_spec */
                $match_spec = $tag_spec_dispatch->tag_specs_by_dispatch["$attr_name=$attr_value"];
                if ($match_spec) {
                    $this->validateTagAgainstSpec($match_spec, $context, $encountered_attributes, $result_for_best_attempt);
                    // If we succeeded
                    if ($result_for_best_attempt->status !== ValidationResultStatus::FAIL) {
                        $validationResult->mergeFrom($result_for_best_attempt);
                        return;
                    }
                }
            }
        }

        // we were not able to dispatch based on a dispatch key, try all matching parsed specfications for that tag name
        if ($result_for_best_attempt->status === ValidationResultStatus::FAIL) {
            foreach ($tag_spec_dispatch->all_tag_specs as $parsed_spec) {
                $this->validateTagAgainstSpec($parsed_spec, $context, $encountered_attributes, $result_for_best_attempt);
                // If we succeeded
                if ($result_for_best_attempt->status !== ValidationResultStatus::FAIL) {
                    $validationResult->mergeFrom($result_for_best_attempt);
                    return;
                }
            }
        }
    }

    /**
     * @param ParsedTagSpec $parsed_spec
     * @param Context $context
     * @param array $encountered_attributes
     * @param IValidationResult $result_for_best_attempt
     */
    public function validateTagAgainstSpec(ParsedTagSpec $parsed_spec, Context $context, array $encountered_attributes, IValidationResult $result_for_best_attempt)
    {
        // We should be failing so far, otherwise why are we being called?
        assert($result_for_best_attempt->status === ValidationResultStatus::FAIL);

        $result_for_attempt = new IValidationResult();
        $result_for_attempt->status = ValidationResultStatus::UNKNOWN;
        $parsed_spec->validateAttributes($context, $encountered_attributes, $result_for_attempt);
        $parsed_spec->validateParentTag($context, $result_for_attempt);
        $parsed_spec->validateAncestorTags($context, $result_for_attempt);

        if ($result_for_attempt->status === ValidationResultStatus::FAIL) {
            // @todo think about this. Some max specificity stuff is here
            return;
        }

        // We succeeded as we haven't exited so far!
        $result_for_best_attempt->status = $result_for_attempt->status;
        $result_for_best_attempt->errors = $result_for_attempt->errors;

        if ($parsed_spec->shouldRecordTagspecValidated()) {
            $is_unique = $context->recordTagspecValidated($parsed_spec);
            if ($parsed_spec->getSpec()->unique && $is_unique !== true) {
                /** @var ParsedTagSpec $spec */
                $spec = $parsed_spec->getSpec();
                $context->addError(ValidationErrorCode::DUPLICATE_UNIQUE_TAG, [getDetailOrName($spec)], $spec->spec_url, $result_for_best_attempt);
                return;
            }
        }

        if (!empty($parsed_spec->getSpec()->mandatory_alternatives)) {
            $satisfied = $parsed_spec->getSpec()->mandatory_alternatives;
            $context->recordMandatoryAlternativesSatisfied($satisfied);
        }
    }

    /**
     * @param Context $context
     * @param IValidationResult $validation_result
     */
    public function maybeEmitMandatoryTagValidationErrors(Context $context, IValidationResult $validation_result)
    {
        /** @var ParsedTagSpec $parsed_tag_spec */
        foreach ($this->mandatory_tag_specs as $parsed_tag_spec) {
            $tagspec = $parsed_tag_spec->getSpec();
            if (!$context->getTagspecsValidated()->contains($parsed_tag_spec)) {
                $context->addError(ValidationErrorCode::MANDATORY_TAG_MISSING, [getDetailOrName($tagspec)], $tagspec->spec_url, $validation_result);
                return;
            }
        }
    }

    /**
     * @param Context $context
     * @param IValidationResult $validation_result
     */
    public function maybeEmitAlsoRequiresValidationErrors(Context $context, IValidationResult $validation_result)
    {
        /** @var ParsedTagSpec $parsed_tag_spec */
        foreach ($context->getTagspecsValidated() as $parsed_tag_spec) {
            /** @var TagSpec $tagspec_require */
            foreach ($parsed_tag_spec->getAlsoRequires() as $tagspec_require) {
                $parsed_tag_spec_require = $this->all_parsed_specs_by_specs[$tagspec_require];
                assert(!empty($parsed_tag_spec_require)); // @todo leave as an assert?
                if (!$context->getTagspecsValidated()->contains($parsed_tag_spec_require)) {
                    if (!$context->addError(ValidationErrorCode::TAG_REQUIRED_BY_MISSING, [getDetailOrName($parsed_tag_spec_require->getSpec()), getDetailOrName($parsed_tag_spec->getSpec())], $parsed_tag_spec->getSpec()->spec_url, $validation_result)) {
                        return;
                    }
                }
            }
        }
    }

    public function maybeEmitMandatoryAlternativesSatisfiedErrors(Context $context, IValidationResult $validation_result)
    {
        /** @var  $satisfied_alternatives */
        $satisfied_alternatives = $context->getMandatoryAlternativesSatisfied();
        $missing_mandatory_alternatives = [];
        /** @var ParsedTagSpec $parsed_tag_spec */
        foreach ($this->all_parsed_specs_by_specs as $tagspec => $parsed_tag_spec) {
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

    public function maybeEmitGlobalTagValidationErrors(Context $context, IValidationResult $validation_result)
    {
        if ($context->getProgress($validation_result)['complete']) {
            return;
        }
        $this->maybeEmitMandatoryTagValidationErrors($context, $validation_result);
        if ($context->getProgress($validation_result)['complete']) {
            return;
        }
        $this->maybeEmitAlsoRequiresValidationErrors($context, $validation_result);
        if ($context->getProgress($validation_result)['complete']) {
            return;
        }
        $this->maybeEmitMandatoryAlternativesSatisfiedErrors($context, $validation_result);
    }
}

/**
 * Get the detail string of the Tag or if not available, the tag name
 * @param TagSpec $spec
 * @return string
 */
function getDetailOrName(TagSpec $spec)
{
    //if ($spec instanceof ParsedTagSpec) {
    //    $spec = $spec->getSpec();
    //}
    return empty($spec->detail) ? $spec->detail : $spec->name;
}

/**
 * @param TagSpec $tag_spec
 * @param array $detail_or_names_to_track
 * @return bool
 */
function shouldRecordTagspecValidated(TagSpec $tag_spec, array $detail_or_names_to_track)
{
    return $tag_spec->mandatory || $tag_spec->unique || (!empty(getDetailOrName($tag_spec)) && isset($detail_or_names_to_track[getDetailOrName($tag_spec)]));
}
