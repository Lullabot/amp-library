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

use Lullabot\AMP\Spec\AttrList;
use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\ChildTagSpec;
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
    protected $also_requires_tagspec = [];
    /** @var ParsedAttrSpec */
    protected $dispatch_key_attr_spec = null;
    /** @var TagSpec[] */
    protected $implicit_attr_specs = [];

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

            if (!empty($parsed_attr_spec->getSpec()->implicit)) {
                $this->implicit_attr_specs[] = $parsed_attr_spec;
            }
        }

        /** @var string $also_require_tag */
        foreach ($tag_spec->also_requires_tag as $also_require_tag) {
            $this->also_requires_tagspec[] = $tagspec_by_detail_or_name[$also_require_tag];
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
        /** @var string $attr_name */
        $attr_name = $this->dispatch_key_attr_spec->getSpec()->name;
        assert(!empty($attr_name));
        /** @var string $mandatory_parent */
        $mandatory_parent = empty($this->spec->mandatory_parent) ? '' : $this->spec->mandatory_parent;
        $attr_value = $this->dispatch_key_attr_spec->getSpec()->value;
        if (empty(($attr_value))) {
            $attr_value = '';
        }
        return TagSpecDispatch::makeDispatchKey($attr_name, $attr_value, $mandatory_parent);
    }

    /**
     * @return TagSpec[]
     */
    public function getAlsoRequiresTagspec()
    {
        return $this->also_requires_tagspec;
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
                    [$this->spec->tag_name, $context->getParentTagName(), $this->getSpec()->mandatory_parent],
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
                        [$this->spec->tag_name, $mandatory_ancestor, $this->spec->mandatory_ancestor_suggested_alternative],
                        $this->spec->spec_url, $validation_result);
                } else {
                    $context->addError(ValidationErrorCode::MANDATORY_TAG_ANCESTOR,
                        [$this->spec->tag_name, $mandatory_ancestor], $this->spec->spec_url, $validation_result);
                }
                return;
            }
        }

        if (!empty($this->spec->disallowed_ancestor)) {
            foreach ($this->spec->disallowed_ancestor as $disallowed_ancestor) {
                if (false !== array_search($disallowed_ancestor, $context->getAncestorTagNames())) {
                    $context->addError(ValidationErrorCode::DISALLOWED_TAG_ANCESTOR,
                        [$this->spec->tag_name, $disallowed_ancestor], $this->spec->spec_url, $validation_result);
                    return;
                }
            }
        }
    }

    /**
     * @param Context $context
     * @param SValidationResult $validation_result
     */
    public function validateChildTags(Context $context, SValidationResult $validation_result)
    {
        /** @var ChildTagSpec|null $child_tag_spec */
        $child_tag_spec = $this->spec->child_tags;
        if (empty($child_tag_spec)) {
            return;
        }

        /** @var string[] $child_tag_names */
        $child_tag_names = $context->getChildTagNames();
        $num_child_tags = count($child_tag_names);

        /** @var string[]|null $first_name_oneof */
        $first_name_oneof = $child_tag_spec->first_child_tag_name_oneof;
        if (!empty($first_name_oneof) && !in_array($child_tag_names[0], $first_name_oneof)) {
            $allowed_names = '[' . join(',', $first_name_oneof) . ']';
            $context->addError(ValidationErrorCode::DISALLOWED_FIRST_CHILD_TAG_NAME,
                [$child_tag_names[0], $this->spec->tag_name, $allowed_names], $this->spec->spec_url, $validation_result);
        }

        /** @var string[]|null $child_tag_name_oneof */
        $child_tag_name_oneof = $child_tag_spec->child_tag_name_oneof;
        if (!empty($child_tag_name_oneof)) {
            foreach ($child_tag_names as $child_tag_name) {
                if (!in_array($child_tag_name, $child_tag_name_oneof)) {
                    $allowed_names = '[' . join(',', $child_tag_name_oneof) . ']';
                    $context->addError(ValidationErrorCode::DISALLOWED_CHILD_TAG_NAME,
                        [$child_tag_name, $this->spec->tag_name, $allowed_names], $this->spec->spec_url, $validation_result);
                }
            }
        }

        /** @var number|null $mandatory_num_child_tags */
        $mandatory_num_child_tags = $child_tag_spec->mandatory_num_child_tags;
        if (is_numeric($mandatory_num_child_tags) && $num_child_tags < $mandatory_num_child_tags) {
            $context->addError(ValidationErrorCode::INCORRECT_NUM_CHILD_TAGS,
                [$this->spec->tag_name, $mandatory_num_child_tags, $num_child_tags], $this->spec->spec_url, $validation_result);
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
        $parsed_attr_specs_validated = new \SplObjectStorage(); // Treat as a set of objects
        $parsed_trigger_specs = [];

        foreach ($this->implicit_attr_specs as $parsed_attr_spec) {
            $parsed_attr_specs_validated->attach($parsed_attr_spec);
        }
        /**
         * @var string $encountered_attr_key
         * @var string $encountered_attr_value
         */
        $should_not_check = false;
        foreach ($encountered_attrs as $encountered_attr_key => $encountered_attr_value) {
            // if ever set something like null in weird situations, just normalize to empty string
            if ($encountered_attr_value === null) {
                $encountered_attr_value = '';
            }

            $encountered_attr_name = mb_strtolower($encountered_attr_key, 'UTF-8');
            $parsed_attr_spec = isset($this->attrs_by_name[$encountered_attr_name]) ?
                $this->attrs_by_name[$encountered_attr_name] : null;
            if (empty($parsed_attr_spec)) {
                if ($this->validateAttrNotFoundInSpec($encountered_attr_name, $context, $result_for_attempt)) {
                    // the attribute, even though not found in specification, was valid
                    continue;
                } else {
                    $should_not_check = true;
                    continue;
                }
            }
            /** @var AttrSpec $attr_spec */
            $attr_spec = $parsed_attr_spec->getSpec();
            if (!empty($attr_spec->deprecation)) {
                $context->addError(ValidationErrorCode::DEPRECATED_ATTR,
                    [$encountered_attr_name, self::getDetailOrName($this->spec), $attr_spec->deprecation],
                    $attr_spec->deprecation_url, $result_for_attempt, $encountered_attr_name);
                // Don't exit as its not a fatal error
            }

            if (isset($attr_spec->value)) {
                $encountered_attr_value_lower = mb_strtolower($encountered_attr_value, 'UTF-8');
                $attr_spec_value_lower = mb_strtolower($attr_spec->value, 'UTF-8');
                if ($encountered_attr_value_lower !== $attr_spec_value_lower) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                        [$encountered_attr_name, self::getDetailOrName($this->spec), $encountered_attr_value],
                        $this->spec->spec_url, $result_for_attempt, $encountered_attr_name);
                    $should_not_check = true;
                    continue;
                }
            }

            if (isset($attr_spec->value_regex)) {
                // notice the use of & as start and end delimiters. Want to avoid use of '/' as it will be in regex, unescaped
                $value_regex = '&(*UTF8)^(' . $attr_spec->value_regex . ')$&i';
                // if it _doesn't_ match its an error
                if (!preg_match($value_regex, $encountered_attr_value)) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                        [$encountered_attr_name, self::getDetailOrName($this->spec), $encountered_attr_value],
                        $this->spec->spec_url, $result_for_attempt, $encountered_attr_name);
                    $should_not_check = true;
                    continue;
                }
            }

            if (isset($attr_spec->value_url)) {
                $parsed_attr_spec->validateAttrValueUrl($context, $encountered_attr_name, $encountered_attr_value,
                    $this->spec, $this->spec->spec_url, $result_for_attempt);
                if ($result_for_attempt->status === ValidationResultStatus::FAIL) {
                    $should_not_check = true;
                    continue;
                }
            }

            if (isset($attr_spec->value_properties)) {
                $parsed_attr_spec->validateAttrValueProperties($context, $encountered_attr_name, $encountered_attr_value,
                    $this->spec, $this->spec->spec_url, $result_for_attempt);
                if ($result_for_attempt->status === ValidationResultStatus::FAIL) {
                    $should_not_check = true;
                    continue;
                }
            }

            if (isset($attr_spec->blacklisted_value_regex)) {
                // notice the use of & as start and end delimiters. Want to avoid use of '/' as it will be in regex, unescaped
                $blacklisted_value_regex = '&(*UTF8)' . $attr_spec->blacklisted_value_regex . '&i';
                // If it matches its an error
                if (preg_match($blacklisted_value_regex, $encountered_attr_value)) {
                    $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                        [$encountered_attr_name, self::getDetailOrName($this->spec), $encountered_attr_value],
                        $this->spec->spec_url, $result_for_attempt, $encountered_attr_name);
                    $should_not_check = true;
                    continue;
                }
            }

            if ($attr_spec->mandatory) {
                $mandatory_attrs_seen->attach($parsed_attr_spec);
            }

            // if the mandatory oneofs had already been seen, its an error
            if ($attr_spec->mandatory_oneof && isset($mandatory_oneofs_seen[$attr_spec->mandatory_oneof])) {
                $context->addError(ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS,
                    [self::getDetailOrName($this->spec), $attr_spec->mandatory_oneof],
                    $this->spec->spec_url, $result_for_attempt, $attr_spec->name);
                $should_not_check = true;
                continue;
            }

            if (!empty($attr_spec->mandatory_oneof)) {
                // Treat as a Set
                $mandatory_oneofs_seen[$attr_spec->mandatory_oneof] = $attr_spec->mandatory_oneof;
            }

            if ($parsed_attr_spec->hasTriggerSpec() && $parsed_attr_spec->getTriggerSpec()->hasIfValueRegex()) {
                $if_value_regex = $parsed_attr_spec->getTriggerSpec()->getIfValueRegex();
                if (preg_match($if_value_regex, $encountered_attr_value)) {
                    $parsed_trigger_specs[] = $parsed_attr_spec->getTriggerSpec();
                }
            }

            // Treat as a Set
            $parsed_attr_specs_validated->attach($parsed_attr_spec);
        }

        // If we've already encountered an error before, then $should_not_check will be true.
        // In that case simply return without executing mandatory one of checks and mandatory attributes
        // In some ways this the best of both worlds: The canonical javascript validator returns as soon
        // as it sees an error in this method. We keep 'continue'-ing and try to see issues with other
        // attributes. In some ways our reporting is more complete and helps later on with html correction.
        //
        // (Note that we don't want to check for mandatory oneofs/attributes if an error has been encountered
        // because we might get un-useful errors that will change the specificity of the validation error and clobber
        // the most appropriate result)
        if ($should_not_check) {
            return;
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

        /** @var ParsedAttrSpec $mandatory_attr */
        foreach ($this->mandatory_attrs as $mandatory_attr) {
            if (!$mandatory_attrs_seen->contains($mandatory_attr)) {
                $context->addError(ValidationErrorCode::MANDATORY_ATTR_MISSING,
                    [$mandatory_attr->getSpec()->name, self::getDetailOrName($this->spec)],
                    $this->spec->spec_url, $result_for_attempt, $mandatory_attr->getSpec()->name);
            }
        }

        /** @var ParsedAttrTriggerSpec $parsed_trigger_spec */
        foreach ($parsed_trigger_specs as $parsed_trigger_spec) {
            foreach ($parsed_trigger_spec->getSpec()->also_requires_attr as $required_attr_name) {
                $parsed_attr_spec = isset($this->attrs_by_name[$required_attr_name]) ?
                    $this->attrs_by_name[$required_attr_name] : null;
                if ($parsed_attr_spec === null) {
                    continue;
                }
                if (!$parsed_attr_specs_validated->contains($parsed_attr_spec)) {
                    $context->addError(ValidationErrorCode::ATTR_REQUIRED_BUT_MISSING,
                        [$parsed_attr_spec->getSpec()->name, self::getDetailOrName($this->spec), $parsed_trigger_spec->getAttrName()],
                        $this->spec->spec_url, $result_for_attempt, $parsed_attr_spec->getSpec()->name);
                }
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
        return empty($tag_spec->spec_name) ? $tag_spec->tag_name : $tag_spec->spec_name;
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

