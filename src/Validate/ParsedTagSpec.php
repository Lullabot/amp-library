<?php

use Lullabot\AMP\Spec\AttrList;
use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;

class ParsedTagSpec
{
    /** @var TagSpec */
    protected $spec;
    /** @var ParsedAttrSpec[] */
    protected $attrs_by_name = [];
    /** @var ParsedAttrSpec[] */
    protected $mandatory_attrs = [];

    // Basically a Set
    /** @var array */
    protected $mandatory_oneofs = [];
    /** @var boolean */
    protected $should_record_tagspec_validated = false;
    /** @var TagSpec[] */
    protected $also_requires = [];
    /** @var ParsedAttrSpec */
    protected $dispatch_key_attr_spec = null;

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
        $attrs = getAttrsFor($tag_spec, $attr_lists_by_name);
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
     * @return TagSpec[]
     */
    public function getAlsoRequires()
    {
        return $this->also_requires;
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
            $parent = $context->getTag()->parentNode;
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

    // No support for templates at the moment
    public function validateAttrNotFoundInSpec($attr_name, Context $context, IValidationResult $validation_result)
    {
        if (strpos($attr_name, 'data-') === 0) {
            return true;
        }

        return false;
    }

    // No support for templates at the moment
    public function validateAttributes(Context $context, array $encountered_attrs, $result_for_attempt)
    {
        // skip layout validation for now

        /** @var $mandatory_attrs_seen */
        $mandatory_attrs_seen = new SplObjectStorage();
        $mandatory_oneofs_seen = []; // Set
        foreach ($encountered_attrs as $encountered_attr_key => $encounted_attr_value) {
            if (empty($encounted_attr_value)) {
                $encounted_attr_value = '';
            }

            $encountered_attr_name = mb_strtolower($encounted_attr_value);
            $parsed_attr_spec = $this->attrs_by_name[$encountered_attr_name];
            if (empty($parsed_attr_spec)) {
                if ($this->validateAttrNotFoundInSpec($encountered_attr_name, $context, $result_for_attempt)) {
                    continue;
                } else {
                    return;
                }
            }

            if (!empty($parsed_attr_spec->getSpec()->deprecation)) {
                $context->addError(ValidationErrorCode::DEPRECATED_ATTR, [$encountered_attr_name, getDetailOrName($this->spec), $parsed_attr_spec->getSpec()->deprecation], $parsed_attr_spec->getSpec()->deprecation_url, $result_for_attempt);
                // Dont exit as its not a fatal error
            }

            if (!empty($parsed_attr_spec->getSpec()->value)) {
                if ($encounted_attr_value != $parsed_attr_spec->getSpec()->value) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE, [$encountered_attr_name, getDetailOrName($this->spec), $encounted_attr_value], $this->spec->spec_url);
                    return;
                }
            }

            if (!empty($parsed_attr_spec->getSpec()->value_regex)) {
                // @todo
            }

            if (!empty($parsed_attr_spec->getSpec()->value_url)) {
                // @todo
            }

            if (!empty($parsed_attr_spec->getSpec()->value_properties)) {
                // @todo
            }

            if (!empty($parsed_attr_spec->getSpec()->blacklisted_value_regex)) {
                // @todo
            }

            if ($parsed_attr_spec->getSpec()->mandatory) {
                $mandatory_attrs_seen->attach($parsed_attr_spec);
            }

            if ($parsed_attr_spec->getSpec()->mandatory_oneof && isset($mandatory_oneofs_seen[$parsed_attr_spec->getSpec()->mandatory_oneof])) {
                $context->addError(ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS, [getDetailOrName($this->spec), $parsed_attr_spec->getSpec()->mandatory_oneof], $this->spec->spec_url, $result_for_attempt);
                return;
            }

        }

        foreach ($this->mandatory_oneofs as $mandatory_oneof) {
            if (!isset($mandatory_oneofs_seen[$mandatory_oneof])) {
                $context->addError(ValidationErrorCode::MANDATORY_ONEOF_ATTR_MISSING, [getDetailOrName($this->spec), $mandatory_oneof], $this->spec->spec_url, $result_for_attempt);
            }
        }

        /** @var ParsedTagSpec $mandatory_attr */
        foreach ($this->mandatory_attrs as $mandatory_attr) {
            if (!$mandatory_attrs_seen->contains($mandatory_attr)) {
                $context->addError(ValidationErrorCode::MANDATORY_ATTR_MISSING, [$mandatory_attr->getSpec()->name, getDetailOrName($this->spec)], $this->spec->spec_url, $result_for_attempt);
            }
        }
    }

}

/**
 * @param TagSpec $tag_spec
 * @param AttrList[] $attr_lists_by_name
 * @return AttrSpec[]
 */
function getAttrsFor(TagSpec $tag_spec, array $attr_lists_by_name)
{
    $attrs = [];
    $names_seen = []; // A Set

    // Layout attributes
    if (!empty($tag_spec->amp_layout)) {
        $layout_specs = $attr_lists_by_name['$AMP_LAYOUT_ATTRS'];
        if (!empty($layout_specs)) {
            foreach ($layout_specs->attrs as $attr_spec) {
                if (!isset($names_seen[$attr_spec->name])) {
                    $names_seen[$attr_spec->name] = 1;
                    $attrs[] = $attr_spec;
                }
            }
        }
    }

    // Attributes specified in the tag specification itself
    /** @var AttrSpec $attr_spec */
    foreach ($tag_spec->attrs as $attr_spec) {
        if (!isset($names_seen[$attr_spec->name])) {
            $names_seen[$attr_spec->name] = 1;
            $attrs[] = $attr_spec;
        }
    }

    // Attributes specified as attribute lists
    /** @var string $attr_list */
    foreach ($tag_spec->attr_lists as $attr_list) {
        $attr_list_specs = $attr_lists_by_name[$attr_list];
        if (!empty($attr_list_specs)) {
            foreach ($attr_list_specs->attrs as $attr_spec) {
                if (!isset($names_seen[$attr_spec->name])) {
                    if (!isset($names_seen[$attr_spec->name])) {
                        $names_seen[$attr_spec->name] = 1;
                        $attrs[] = $attr_spec;
                    }
                }
            }
        }
    }

    // Global attributes, common to all tags
    $global_specs = $attr_lists_by_name['$GLOBAL_ATTRS'];
    if (empty($global_specs)) {
        return $attrs;
    }

    /** @var AttrSpec $attr_spec */
    foreach ($global_specs->attrs as $attr_spec) {
        if (!isset($names_seen[$attr_spec->name])) {
            $names_seen[$attr_spec->name] = 1;
            $attrs[] = $attr_spec;
        }
    }

    return $attrs;
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
