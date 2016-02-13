<?php

use Lullabot\AMP\Spec\AttrList;
use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationResult;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Spec\ValidatorRules;

class ParsedValidatorRules
{
    /** @var TagSpecDispatch[] */
    public $tag_dispatch_by_tag_name = [];
    /** @var ParsedTagSpec[] */
    public $mandatory_tag_specs = [];
    /** @var ValidatorRules */
    public $rules;
    /** @var ErrorFormat[] */
    protected $format_by_code = [];

    public function __construct(ValidatorRules $rules)
    {
        $this->rules = $rules;

        /** @var AttrList[] $attr_lists_by_name */
        $attr_lists_by_name = [];
        foreach ($this->rules->attr_lists as $attr_list_obj) {
            $attr_lists_by_name[$attr_list_obj->name] = $attr_list_obj;
        }

        $tagspec_by_detail_or_name = [];
        // This is a set
        $detail_or_names_to_track = [];
        /** @var TagSpec $tag_spec */
        foreach ($this->rules->tags as $tag_spec) {
            assert(empty($tagspec_by_detail_or_name[getDetailOrName($tag_spec)]));
            $tagspec_by_detail_or_name[getDetailOrName($tag_spec)] = $tag_spec;

            if (!empty($tag_spec->also_requires)) {
                $detail_or_names_to_track[getDetailOrName($tag_spec)] = 1;
            }

            foreach ($tag_spec->also_requires as $require) {
                $detail_or_names_to_track[$require] = 1;
            }
        }

        /** @var TagSpec $tag_spec */
        foreach ($this->rules->tags as $tag_spec) {
            /** @var ParsedTagSpec $parsed_tag_spec */
            $parsed_tag_spec = new ParsedTagSpec($attr_lists_by_name, $tagspec_by_detail_or_name,
                shouldRecordTagspecValidated($tag_spec, $detail_or_names_to_track), $tag_spec);
            assert(!empty($tag_spec->name));

            if (!isset($this->tag_dispatch_by_tag_name[$tag_spec->name])) {
                $this->tag_dispatch_by_tag_name[$tag_spec->name] = new TagSpecDispatch();
            }

            /** @var TagSpecDispatch $tagname_dispatch */
            $tagname_dispatch = $this->tag_dispatch_by_tag_name[$tag_spec->name];
            if ($parsed_tag_spec->hasDispatchKey()) {
                $tagname_dispatch->tag_specs_by_dispatch[$parsed_tag_spec->getDispatchKey()] = $parsed_tag_spec;
            }

            $tagname_dispatch->all_tag_specs[] = $parsed_tag_spec;

            if ($tag_spec->mandatory) {
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

        if (!empty($tag_spec_dispatch->tag_specs_by_dispatch)) {
            foreach ($encountered_attributes as $attr_name => $attr_value) {
                if ($attr_name === $attr_value) {
                    $attr_value = '';
                }

                $attr_name = mb_strtolower($attr_name);
                /** @var ParsedTagSpec $match_spec */
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

    /**
     * @param ParsedTagSpec $parsed_spec
     * @param Context $context
     * @param array $encountered_attributes
     * @param IValidationResult $result_for_best_attempt
     */
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
                /** @var ParsedTagSpec $spec */
                $spec = $parsed_spec->getSpec();
                $context->addError(ValidationErrorCode::DUPLICATE_UNIQUE_TAG, [getDetailOrName($spec)], $spec->spec_url, $result_for_best_attempt);
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

    public function recordMandatoryAlternativeSatisfied(/* @var string */
        $satisfied)
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

/**
 * @param TagSpec $tag_spec
 * @param AttrSpec[][] $attr_lists_by_name
 * @return AttrSpec[]
 */
function GetAttrsFor($tag_spec, $attr_lists_by_name)
{
}

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

class ParsedTagSpec
{
    /** @var TagSpec */
    public $spec;
    /** @var ParsedAttrSpec[] */
    public $attrs_by_name = [];
    /** @var ParsedAttrSpec[] */
    public $mandatory_attrs = [];

    // Basically a Set
    /** @var array */
    public $mandatory_oneofs = [];
    /** @var boolean */
    public $should_record_tagspec_validated = false;
    /** @var TagSpec[] */
    public $also_requires = [];
    /** @var ParsedAttrSpec */
    public $dispatch_key_attr_spec = null;

    /**
     * ParsedTagSpec constructor.
     * @param AttrList[] $attr_lists_by_name
     * @param  $tagspec_by_detail_or_name
     * @param boolean $should_record_tagspec_validated
     * @param TagSpec $tag_spec
     */
    public function __construct(array $attr_lists_by_name, array $tagspec_by_detail_or_name, $should_record_tagspec_validated, TagSpec $tag_spec)
    {
        $this->spec = $tag_spec;
        $this->should_record_tagspec_validated = $should_record_tagspec_validated;

        /** @var AttrSpec[] $attrs */
        $attrs = GetAttrsFor($tag_spec, $attr_lists_by_name);
        foreach ($attrs as $attr) {
            $parsed_attr_spec = new ParsedAttrSpec($attr);
            $this->attrs_by_name[$attr->name] = $parsed_attr_spec;
            if ($parsed_attr_spec->getSpec()->mandatory) {
                $this->mandatory_attrs[] = $parsed_attr_spec;
            }

            $mandatory_oneofs = $parsed_attr_spec->getSpec()->mandatory_oneof;
            if (!empty($mandatory_oneofs)) {
                foreach ($mandatory_oneofs as $mandatory_oneof) {
                    // Treat this like a set
                    $this->mandatory_oneofs[$mandatory_oneof] = 1;
                }
            }

            $alt_names = $parsed_attr_spec->getSpec()->alternative_names;
            foreach ($alt_names as $alt_name) {
                $this->attrs_by_name[$alt_name] = $parsed_attr_spec;
            }

            if ($parsed_attr_spec->getSpec()->dispatch_key) {
                $this->dispatch_key_attr_spec = $parsed_attr_spec;
            }
        }

        // Is this even required?
        // ksort($this->mandatory_oneofs);

        foreach ($tag_spec->also_requires as $also_require) {
            $this->also_requires[] = $tagspec_by_detail_or_name[$also_require];
        }
    }

    /**
     * @return TagSpec
     */
    public function getSpec()
    {
        return $this->spec;
    }

    /**
     * @return boolean
     */
    public function hasDispatchKey()
    {
        return !empty($this->dispatch_key_attr_spec);
    }

    /**
     * @return string
     */
    public function getDispatchKey()
    {
        assert($this->hasDispatchKey());
        $key = $this->dispatch_key_attr_spec->getSpec()->name;
        $value = $this->dispatch_key_attr_spec->getSpec()->value;
        return "$key=$value";
    }

    /**
     * @return \Lullabot\AMP\Spec\TagSpec[]
     */
    public function getAlsoRequires()
    {
        return $this->also_requires;
    }

    public function validateAttributes($context, $encountered_attributes, $result_for_attempt)
    {
        // @todo
        return;
    }

    /**
     * @return bool
     */
    public function shouldRecordTagspecValidated()
    {
        return $this->should_record_tagspec_validated;
    }

    /**
     * @param Context $context
     * @param IValidationResult $validation_result
     */
    public function validateParentTag(Context $context, IValidationResult $validation_result)
    {
        if ($this->getSpec()->mandatory_parent) {
            $parent = $context->tag->parentNode;
            if ($parent->tagName !== $this->getSpec()->mandatory_parent) {
                $context->addError(ValidationErrorCode::WRONG_PARENT_TAG, [$this->spec->name, $parent->tagName, $this->getSpec()->mandatory_parent], $this->spec->spec_url, $validation_result);
            }
        }
    }

    public function validateAncestorTags($context, $result_for_attempt)
    {
        // @todo
        return;
    }

}

class TagSpecDispatch
{
    /** @var ParsedTagSpec[] */
    public $all_tag_specs = [];
    /** @var ParsedTagSpec[] */
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

/**
 * @param TagSpec $tag_spec
 * @param array $detail_or_names_to_track
 * @return bool
 */
function shouldRecordTagspecValidated(TagSpec $tag_spec, array $detail_or_names_to_track)
{
    return $tag_spec->mandatory || $tag_spec->unique || (!empty(getDetailOrName($tag_spec)) && isset($detail_or_names_to_track[getDetailOrName($tag_spec)]));
}


class IValidationResult extends ValidationResult
{
    public function mergeFrom(IValidationResult $result)
    {
        // @todo
    }
}