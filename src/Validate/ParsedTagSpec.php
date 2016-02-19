<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\AttrList;
use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;

/**
 * Class ParsedTagSpec
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedTagSpec class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The static methods getAttrsFor(), getDetailOrName(), shouldRecordTagspecValidated() are normal top-level functions
 * in validator.js but have been incorporated into this class, when they were ported, for convenience.
 *
 * Note:
 *  - shouldRecordTagspecValidated() in validator.js has been renamed shouldRecordTagspecValidatedTest() to prevent
 *    a name collision in this class
 *  - getAttrsFor() static method is called GetAttrsFor() in validator.js
 *
 */
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
     * @param TagSpec[] $tagspec_by_detail_or_name
     * @param boolean $should_record_tagspec_validated
     * @param TagSpec $tag_spec
     */
    public function __construct(array $attr_lists_by_name, array $tagspec_by_detail_or_name,
                                $should_record_tagspec_validated, TagSpec $tag_spec)
    {
        $this->spec = $tag_spec;
        $this->should_record_tagspec_validated = $should_record_tagspec_validated;

        /** @var AttrSpec[] $attr_specs */
        $attr_specs = self::getAttrsFor($tag_spec, $attr_lists_by_name);
        foreach ($attr_specs as $attr_spec) {
            $parsed_attr_spec = new ParsedAttrSpec($attr_spec);
            $this->attrs_by_name[$attr_spec->name] = $parsed_attr_spec;
            if ($parsed_attr_spec->getSpec()->mandatory) {
                $this->mandatory_attrs[] = $parsed_attr_spec;
            }

            /** @var string $mandatory_oneofs */
            $mandatory_oneofs = $parsed_attr_spec->getSpec()->mandatory_oneof;
            if (!empty($mandatory_oneofs)) {
                // Treat this like a set
                $this->mandatory_oneofs[$mandatory_oneofs] = $mandatory_oneofs;
            }

            $alt_names = $parsed_attr_spec->getSpec()->alternative_names;
            foreach ($alt_names as $alt_name) {
                $this->attrs_by_name[$alt_name] = $parsed_attr_spec;
            }

            if ($parsed_attr_spec->getSpec()->dispatch_key) {
                $this->dispatch_key_attr_spec = $parsed_attr_spec;
            }
        }

        /** @var string $also_require */
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
        assert($this->hasDispatchKey() === true);
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
     * @return boolean
     */
    public function shouldRecordTagspecValidated()
    {
        return $this->should_record_tagspec_validated;
    }

    /**
     * @param Context $context
     * @param SValidationResult $validation_result
     */
    public function validateParentTag(Context $context, SValidationResult $validation_result)
    {
        if (!empty($this->spec->mandatory_parent)) {
            if ($context->getParentTagName() !== $this->spec->mandatory_parent) {
                $context->addError(ValidationErrorCode::WRONG_PARENT_TAG,
                    [$this->spec->name, $context->getParentTagName(), $this->getSpec()->mandatory_parent],
                    $this->spec->spec_url, $validation_result);
            }
        }
    }

    /**
     * @param Context $context
     * @param SValidationResult $validation_result
     */
    public function validateAncestorTags(Context $context, SValidationResult $validation_result)
    {
        if (!empty($this->spec->mandatory_ancestor)) {
            $mandatory_ancestor = $this->spec->mandatory_ancestor;
            if (false === array_search($mandatory_ancestor, $context->getAncestorTagNames())) {
                if (!empty($this->spec->mandatory_ancestor_suggested_alternative)) {
                    $context->addError(ValidationErrorCode::MANDATORY_TAG_ANCESTOR_WITH_HINT,
                        [$this->spec->name, $mandatory_ancestor, $this->spec->mandatory_ancestor_suggested_alternative],
                        $this->spec->spec_url, $validation_result);
                } else {
                    $context->addError(ValidationErrorCode::MANDATORY_TAG_ANCESTOR,
                        [$this->spec->name, $mandatory_ancestor], $this->spec->spec_url, $validation_result);
                }
                return;
            }
        }

        if (!empty($this->spec->disallowed_ancestor)) {
            foreach ($this->spec->disallowed_ancestor as $disallowed_ancestor) {
                if (false !== array_search($disallowed_ancestor, $context->getAncestorTagNames())) {
                    $context->addError(ValidationErrorCode::DISALLOWED_TAG_ANCESTOR,
                        [$this->spec->name, $disallowed_ancestor], $this->spec->spec_url, $validation_result);
                    return;
                }
            }
        }
    }

    /**
     * Deals with attributes not found in AMP specification. These would be all attributes starting with data-
     *
     * No support for templates at the moment
     * returns true if attribute found was valid, false otherwise
     *
     * @param $attr_name
     * @param Context $context
     * @param SValidationResult $validation_result
     * @return bool
     */
    public function validateAttrNotFoundInSpec($attr_name, Context $context, SValidationResult $validation_result)
    {
        if (mb_strpos($attr_name, 'data-', 0, 'UTF-8') === 0) {
            return true;
        }

        $context->addError(ValidationErrorCode::DISALLOWED_ATTR,
            [$attr_name, self::getDetailOrName($this->spec)],
            $this->spec->spec_url, $validation_result, $attr_name);

        return false;
    }

    /**
     * Note: No support for templates and layout validation at the moment
     *
     * @param Context $context
     * @param string[] $encountered_attrs
     * @param SValidationResult $result_for_attempt
     */
    public function validateAttributes(Context $context, array $encountered_attrs, SValidationResult $result_for_attempt)
    {
        // skip layout validation for now

        /** @var \SplObjectStorage $mandatory_attrs_seen */
        $mandatory_attrs_seen = new \SplObjectStorage(); // Treat as a set of objects
        $mandatory_oneofs_seen = []; // Treat as Set of strings
        /**
         * @var string $encountered_attr_key
         * @var string $encountered_attr_value
         */
        foreach ($encountered_attrs as $encountered_attr_key => $encountered_attr_value) {
            // if ever set something like null in weird situations, just normalize to empty string
            if (empty($encountered_attr_value)) {
                $encountered_attr_value = '';
            }

            $encountered_attr_name = mb_strtolower($encountered_attr_key, 'UTF-8');
            $parsed_attr_spec = isset($this->attrs_by_name[$encountered_attr_name]) ?
                $this->attrs_by_name[$encountered_attr_name] : null;
            if (empty($parsed_attr_spec)) {
                $this->validateAttrNotFoundInSpec($encountered_attr_name, $context, $result_for_attempt);
                continue;
            }

            /** @var AttrSpec $attr_spec */
            $attr_spec = $parsed_attr_spec->getSpec();

            // @todo changed the order of checks here. Is that ok?
            if (!empty($attr_spec->mandatory_oneof)) {
                // Treat as a Set
                $mandatory_oneofs_seen[$attr_spec->mandatory_oneof] = $attr_spec->mandatory_oneof;
            }

            // @todo changed the order of checks here. Is that ok?
            if ($attr_spec->mandatory) {
                $mandatory_attrs_seen->attach($parsed_attr_spec);
            }

            if (!empty($attr_spec->deprecation)) {
                $context->addError(ValidationErrorCode::DEPRECATED_ATTR,
                    [$encountered_attr_name, self::getDetailOrName($this->spec), $attr_spec->deprecation],
                    $attr_spec->deprecation_url, $result_for_attempt, $encountered_attr_name);
                continue;
            }

            if (!empty($attr_spec->value)) {
                if ($encountered_attr_value != $attr_spec->value) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                        [$encountered_attr_name, self::getDetailOrName($this->spec), $encountered_attr_value],
                        $this->spec->spec_url, $result_for_attempt, $encountered_attr_name);
                    continue;
                }
            }

            if (!empty($attr_spec->value_regex)) {
                // notice the use of & as start and end delimiters. Want to avoid use of '/' as it will be in regex, unescaped
                $value_regex = '&(*UTF8)^(' . $attr_spec->value_regex . ')$&';
                // if it _doesn't_ match its an error
                if (!preg_match($value_regex, $encountered_attr_value)) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                        [$encountered_attr_name, self::getDetailOrName($this->spec), $encountered_attr_value],
                        $this->spec->spec_url, $result_for_attempt, $encountered_attr_name);
                    continue;
                }
            }

            if (!empty($attr_spec->value_url)) {
                $parsed_attr_spec->validateAttrValueUrl($context, $encountered_attr_name, $encountered_attr_value,
                    $this->spec, $this->spec->spec_url, $result_for_attempt);
                continue;
            }

            if (!empty($attr_spec->value_properties)) {
                $parsed_attr_spec->validateAttrValueProperties($context, $encountered_attr_name, $encountered_attr_value,
                    $this->spec, $this->spec->spec_url, $result_for_attempt);
                continue;
            }

            if (!empty($attr_spec->blacklisted_value_regex)) {
                // notice the use of & as start and end delimiters. Want to avoid use of '/' as it will be in regex, unescaped
                $blacklisted_value_regex = '&(*UTF8)' . $attr_spec->blacklisted_value_regex . '&i';
                // If it matches its an error
                if (preg_match($blacklisted_value_regex, $encountered_attr_value)) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                        [$encountered_attr_name, self::getDetailOrName($this->spec), $encountered_attr_value],
                        $this->spec->spec_url, $result_for_attempt, $encountered_attr_name);
                    continue;
                }
            }

            // if the mandatory oneofs had already been seen, its an error
            if ($attr_spec->mandatory_oneof && isset($mandatory_oneofs_seen[$attr_spec->mandatory_oneof])) {
                $context->addError(ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS,
                    [self::getDetailOrName($this->spec), $attr_spec->mandatory_oneof],
                    $this->spec->spec_url, $result_for_attempt, $attr_spec->name);
                continue;
            }

        }

        // This is to see if any of the mandatory oneof attributes were _not_ seen. Remember, they are mandatory.
        /** @var string $mandatory_oneof */
        foreach ($this->mandatory_oneofs as $mandatory_oneof) {
            if (!isset($mandatory_oneofs_seen[$mandatory_oneof])) {
                $context->addError(ValidationErrorCode::MANDATORY_ONEOF_ATTR_MISSING,
                    [self::getDetailOrName($this->spec), $mandatory_oneof],
                    $this->spec->spec_url, $result_for_attempt); // Can't provide an attribute name here
            }
        }

        /** @var ParsedTagSpec $mandatory_attr */
        foreach ($this->mandatory_attrs as $mandatory_attr) {
            if (!$mandatory_attrs_seen->contains($mandatory_attr)) {
                $context->addError(ValidationErrorCode::MANDATORY_ATTR_MISSING,
                    [$mandatory_attr->getSpec()->name, self::getDetailOrName($this->spec)],
                    $this->spec->spec_url, $result_for_attempt, $mandatory_attr->getSpec()->name);
            }
        }
    }

    /**
     * @param TagSpec $tag_spec
     * @param AttrList[] $attr_lists_by_name
     * @return AttrSpec[]
     */
    public static function getAttrsFor(TagSpec $tag_spec, array $attr_lists_by_name)
    {
        /** @var AttrSpec[] $attr_specs */
        $attr_specs = [];
        $attr_names_seen = []; // A Set of strings

        // Layout attributes
        if (!empty($tag_spec->amp_layout)) {
            if (!empty($attr_lists_by_name['$AMP_LAYOUT_ATTRS'])) {
                $layout_specs = $attr_lists_by_name['$AMP_LAYOUT_ATTRS'];
                /** @var AttrSpec $attr_spec */
                foreach ($layout_specs->attrs as $attr_spec) {
                    if (!isset($attr_names_seen[$attr_spec->name])) {
                        $attr_names_seen[$attr_spec->name] = $attr_spec->name; // Treat as a Set
                        $attr_specs[] = $attr_spec;
                    }
                }
            }
        }

        // Attributes specified in the tag specification itself
        /** @var AttrSpec $attr_spec */
        foreach ($tag_spec->attrs as $attr_spec) {
            if (!isset($attr_names_seen[$attr_spec->name])) {
                $attr_names_seen[$attr_spec->name] = $attr_spec->name; // Treat as a Set
                $attr_specs[] = $attr_spec;
            }
        }

        // Attributes specified as attribute lists
        /** @var string $attr_list */
        foreach ($tag_spec->attr_lists as $attr_list) {
            if (!empty($attr_lists_by_name[$attr_list])) {
                /** @var AttrList $attr_list_specs */
                $attr_list_specs = $attr_lists_by_name[$attr_list];
                /** @var AttrSpec $attr_spec */
                foreach ($attr_list_specs->attrs as $attr_spec) {
                    if (!isset($attr_names_seen[$attr_spec->name])) {
                        $attr_names_seen[$attr_spec->name] = $attr_spec->name; // Treat as a Set
                        $attr_specs[] = $attr_spec;
                    }
                }
            }
        }

        // Global attributes, common to all tags
        if (empty($attr_lists_by_name['$GLOBAL_ATTRS'])) {
            // nothing was found, we're done
            return $attr_specs;
        }

        $global_specs = $attr_lists_by_name['$GLOBAL_ATTRS'];

        /** @var AttrSpec $attr_spec */
        foreach ($global_specs->attrs as $attr_spec) {
            if (!isset($attr_names_seen[$attr_spec->name])) {
                $attr_names_seen[$attr_spec->name] = $attr_spec->name; // Treat as a Set
                $attr_specs[] = $attr_spec;
            }
        }

        return $attr_specs;
    }


    /**
     * @param TagSpec $tag_spec
     * @return string
     */
    public static function getDetailOrName(TagSpec $tag_spec)
    {
        return empty($tag_spec->detail) ? $tag_spec->name : $tag_spec->detail;
    }

    /**
     * @param TagSpec $tag_spec
     * @param array $detail_or_names_to_track
     * @return bool
     */
    public static function shouldRecordTagspecValidatedTest(TagSpec $tag_spec, array $detail_or_names_to_track)
    {
        return $tag_spec->mandatory || $tag_spec->unique ||
        (!empty(self::getDetailOrName($tag_spec)) && isset($detail_or_names_to_track[self::getDetailOrName($tag_spec)]));
    }

}

