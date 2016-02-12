<?php

use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationResult;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;

class ParsedValidatorRules
{
    public $tag_spec_by_tag_name = [];
    public $mandatory_tag_specs = [];
    public $rules;
    protected $format_by_code = [];

    public function __construct(ValidatorRules $rules)
    {
        $this->rules = $rules;

        $attr_lists_by_name = [];
        foreach ($this->rules->attr_lists as $attr_list_obj) {
            $attr_lists_by_name[$attr_list_obj->name] = $attr_list_obj->attrs;
        }

        $tagspec_by_detail_or_name = [];
        // This is a set
        $detail_or_names_to_track = [];
        /** @var \Lullabot\AMP\Spec\TagSpec $tag_spec */
        foreach ($this->rules->tags as $tag_spec) {
            assert(empty($tagspec_by_detail_or_name[getDetailOrName($tag_spec)]));
            $tagspec_by_detail_or_name[getDetailOrName($tag_spec)] = $tag_spec;

            if (!empty($tag_spec->also_requires)) {
                $detail_or_names_to_track[getDetailOrName($tag_spec)] = 1;
            }

            foreach ($tag_spec->also_requires as $require) {
                $detail_or_names_to_track += [$require => 1];
            }
        }

        /** @var \Lullabot\AMP\Spec\TagSpec $tag_spec */
        foreach ($this->rules->tags as $tag_spec) {
            /** @var ParsedTagSpec $parsed_tag_spec */
            $parsed_tag_spec = new ParsedTagSpec($attr_lists_by_name, $tagspec_by_detail_or_name,
                shouldRecordTagspecValidated($tag_spec, $detail_or_names_to_track), $tag_spec);
            assert(!empty($tag_spec->name));

            if (!isset($this->tag_spec_by_tag_name[$tag_spec->name])) {
                $this->tag_spec_by_tag_name[$tag_spec->name] = new TagSpecDispatch();
            }

            /** @var TagSpecDispatch $tagname_dispatch */
            $tagname_dispatch = $this->tag_spec_by_tag_name[$tag_spec->name];
            if ($parsed_tag_spec->hasDispatchKey()) {
                $tagname_dispatch->tag_specs_by_dispatch[$parsed_tag_spec->getDispatchKey()] = $tag_spec;
            }

            $tagname_dispatch->all_tag_specs[] = $tag_spec;

            if ($tag_spec->mandatory) {
                $this->mandatory_tag_specs[] = $tag_spec;
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

    public function validateTag(Context $context, $tag_name, array $encountered_attributes, IValidationResult $validationResult)
    {
        /** @var TagSpecDispatch $tag_spec_dispatch */
        $tag_spec_dispatch = $this->tag_spec_by_tag_name[$tag_name];
        if (empty($tag_spec_dispatch)) {
            $context->addError(ValidationErrorCode::DISALLOWED_TAG, [$tag_name], '', $validationResult);
            return;
        }

        $result_for_best_attempt = new IValidationResult();
        $result_for_best_attempt->status = ValidationResultStatus::FAIL;

        if (!empty($tag_spec_dispatch->tag_specs_by_dispatch)) {
            foreach ($encountered_attributes as $attr_name => $attr_value) {
                if ($attr_name === $attr_value) {
                    $attr_value = '';
                }

                $attr_name = mb_strtolower($attr_name);
                /** @var TagSpec $match_spec */
                $match_spec = $tag_spec_dispatch->tag_specs_by_dispatch["$attr_name=$attr_value"];
                if ($match_spec) {
                    $this->validateTagAgainstSpec($match_spec, $context, $encountered_attributes, $result_for_best_attempt);
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
                if ($result_for_best_attempt->status !== ValidationResultStatus::FAIL) {
                    $validationResult->mergeFrom($result_for_best_attempt);
                    return;
                }
            }
        }
    }

    public function validateTagAgainstSpec(ParsedTagSpec $parsed_spec, Context $context, array $encountered_attributes, IValidationResult $result_for_best_attempt)
    {
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
                /** @var TagSpec $spec */
                $spec = $parsed_spec->getSpec();
                $context->addError(ValidationErrorCode::DUPLICATE_UNIQUE_TAG, [getDetailOrName($parsed_spec)], $spec->spec_url, $result_for_best_attempt);
                return;
            }
        }

        if (!empty($parsed_spec->getSpec()->mandatory_alternatives)) {
            $satisfied = $parsed_spec->getSpec()->mandatory_alternatives;
            $context->recordMandatoryAlternativeSatisfied($satisfied);
        }
    }

}

class Context
{
    /** @var DOMElement */
    public $tag;

    public function recordMandatoryAlternativeSatisfied(/* @var string */ $satisfied)
    {
        // @todo
    }

    public function addError($code, array $params, $spec_url, ValidationResult $validationResult)
    {
        // @todo
    }

    public function recordTagspecValidated(ParsedTagSpec $parsed_spec)
    {
        // @todo. Probably need to add $parsed_spec to an splobjectstorage?
    }
}

class ParsedTagSpec
{
    // protected $spec
    public function __construct(array $attr_lists_by_name, array $tagspec_by_detail_or_name, /* @var boolean */
                                $should_record_tagspec_validated, TagSpec $tag_spec)
    {
        // @todo
    }

    /**
     * @return TagSpec
     */
    public function getSpec()
    {
        // @todo return this->spec
    }

    public function shouldRecordTagspecValidated()
    {

    }

    /**
     * @return boolean
     */
    public function hasDispatchKey()
    {
        // @todo
    }

    /**
     * @return string
     */
    public function getDispatchKey()
    {
        // @todo
    }

    public function validateAttributes($context, $encountered_attributes, $result_for_attempt)
    {
        // @todo
    }

    public function validateParentTag($context, $result_for_attempt)
    {
        // @todo
    }

    public function validateAncestorTags($context, $result_for_attempt)
    {
        // @todo
    }

}

class TagSpecDispatch
{
    public $all_tag_specs = [];
    public $tag_specs_by_dispatch = [];
}

/**
 * @param TagSpec $tag_spec
 * @return string
 */
function getDetailOrName(TagSpec $tag_spec)
{
    return empty($tag_spec->detail) ? $tag_spec->detail : $tag_spec->name;
}

class IValidationResult extends ValidationResult
{
    public function mergeFrom(IValidationResult $result)
    {
        // @todo
    }
}