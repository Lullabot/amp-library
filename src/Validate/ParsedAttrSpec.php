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

use Lullabot\AMP\Spec\AttrSpec;
use Lullabot\AMP\Spec\PropertySpec;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;

/**
 * Class ParsedAttrSpec
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedAttrSpec class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 */
class ParsedAttrSpec
{
    /** @var  AttrSpec */
    public $spec;
    /** @var PropertySpec[] */
    public $value_property_by_name = [];
    /** @var PropertySpec[] */
    public $mandatory_value_property_names = [];
    /** @var ParsedAttrTriggerSpec|null */
    public $trigger_spec = null;
    /** @var ParsedUrlSpec */
    public $value_url_spec;

    public function __construct(AttrSpec $attr_spec)
    {
        $this->spec = $attr_spec;
        // Can pass null
        $this->value_url_spec = new ParsedUrlSpec($this->spec->value_url);

        if (!empty($this->spec->value_properties)) {
            /** @var PropertySpec $property */
            foreach ($this->spec->value_properties->properties as $property) {
                $this->value_property_by_name[$property->name] = $property;
                if ($property->mandatory) {
                    $this->mandatory_value_property_names[$property->name] = $property;
                }
            }
        }

        if (!empty($this->spec->trigger)) {
            $this->trigger_spec = new ParsedAttrTriggerSpec($this->spec);
        }
    }

    /**
     * @return bool
     */
    public function hasTriggerSpec()
    {
        return $this->trigger_spec !== null;
    }

    /**
     * @return ParsedAttrTriggerSpec|null
     */
    public function getTriggerSpec()
    {
        return $this->trigger_spec;
    }

    /**
     * @return AttrSpec
     */
    public function getSpec()
    {
        return $this->spec;
    }

    /**
     * @param Context $context
     * @param string $attr_name
     * @param string $attr_value
     * @param TagSpec $tagspec
     * @param string $spec_url
     * @param SValidationResult $validation_result
     */
    public function validateAttrValueUrl(Context $context, $attr_name, $attr_value, TagSpec $tagspec, $spec_url, SValidationResult $validation_result)
    {
        $maybe_uris = []; // A Set
        if ($attr_name != 'srcset') {
            // Treat as a Set
            $maybe_uris[trim($attr_value)] = 1;
        } else {
            // To deal with cases like srcset="image-1x.png 1x, image-2x.png 2x,image-3x.png 3x, image-4x.png 4x"
            $segments = explode(',', trim($attr_value));
            /** @var string $segment */
            foreach ($segments as $segment) {
                $key_value = explode(' ', trim($segment));
                if (!empty(trim($key_value[0]))) {
                    $maybe_uris[trim($key_value[0])] = 1;
                }
            }
        }

        if (empty($maybe_uris)) {
            $context->addError(ValidationErrorCode::MISSING_URL,
                [$attr_name, ParsedTagSpec::getTagSpecName($tagspec)], $spec_url, $validation_result, $attr_name);
            return;
        }

        /**
         * @var string $maybe_uri
         * @var number $always_one this is a set, value is always 1
         */
        foreach ($maybe_uris as $maybe_uri => $always_one) {
            $unescape_maybe_uri = html_entity_decode($maybe_uri, ENT_HTML5);
            $this->value_url_spec->validateUrlAndProtocolInAttr($context, $attr_name, $unescape_maybe_uri, $tagspec, $validation_result);
            if ($validation_result->status === ValidationResultStatus::FAIL) {
                // No explicit $context->addError as $this->validateUrlAndProtocol would have already done that
                return;
            }
        }
    }

    /**
     * @param Context $context
     * @param string $attr_name
     * @param string $attr_value
     * @param TagSpec $tagspec
     * @param string $spec_url
     * @param SValidationResult $result
     */
    public function validateAttrValueProperties(Context $context, $attr_name, $attr_value, TagSpec $tagspec, $spec_url, SValidationResult $result)
    {
        $segments = explode(',', $attr_value);
        $properties = [];
        $properties_segment = [];
        /** @var string $segment */
        foreach ($segments as $segment) {
            $key_value = explode('=', $segment);
            if (count($key_value) < 2) {
                continue;
            }

            $key_name = trim(mb_strtolower($key_value[0], 'UTF-8'));
            $properties[$key_name] = $key_value[1];
            $properties_segment[$key_name] = $segment;
        }

        foreach ($properties as $name => $value) {
            $segment = $properties_segment[$name];
            if (!isset($this->value_property_by_name[$name])) {
                $context->addError(ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE,
                    [$name, $attr_name, ParsedTagSpec::getTagSpecName($tagspec)], $spec_url, $result, $attr_name, $segment);
                continue;
            }
            /** @var PropertySpec $property_spec */
            $property_spec = $this->value_property_by_name[$name];
            if (!empty($property_spec->value)) {
                if ($property_spec->value != mb_strtolower($value, 'UTF-8')) {
                    $context->addError(ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
                        [$name, $attr_name, ParsedTagSpec::getTagSpecName($tagspec), $value], $spec_url, $result, $attr_name, $segment);
                }
            } else if (!empty($property_spec->value_double)) {
                if (!is_numeric($value) || ((float)$property_spec->value_double) !== ((float)$value)) {
                    $context->addError(ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
                        [$name, $attr_name, ParsedTagSpec::getTagSpecName($tagspec), $value], $spec_url, $result, $attr_name, $segment);
                }
            }
        }

        /** @var PropertySpec $mandatory_value_property_name */
        $names = array_keys($properties);
        foreach ($this->mandatory_value_property_names as $mandatory_value_property_name) {
            if (false === array_search($mandatory_value_property_name->name, $names)) {
                $context->addError(ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE,
                    [$mandatory_value_property_name->name, $attr_name, ParsedTagSpec::getTagSpecName($tagspec)],
                    $spec_url, $result, $attr_name);
            }
        }

    }

    /**
     * @param Context $context
     * @param string $encountered_attr_name
     * @param string $encountered_attr_value
     * @param TagSpec $tag_spec
     * @param SValidationResult $result_for_attempt
     * @return bool
     */
    public function validateNonTemplateAttrValueAgainstSpec(Context $context, $encountered_attr_name, $encountered_attr_value, TagSpec $tag_spec, SValidationResult $result_for_attempt)
    {
        /** @var AttrSpec $attr_spec */
        $attr_spec = $this->getSpec();
        if (isset($attr_spec->value)) {
            // Note: Made case sensitive as this seems to be the way its done in canonical validator
            if ($encountered_attr_value === $attr_spec->value) {
                return true;
            }

            if ($attr_spec->value === '' && $encountered_attr_value == $encountered_attr_name) {
                return true;
            }

            $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                [$encountered_attr_name, ParsedTagSpec::getTagSpecName($tag_spec), $encountered_attr_value],
                $tag_spec->spec_url, $result_for_attempt, $encountered_attr_name);

            return false;
        } else if (isset($attr_spec->value_regex) || isset($attr_spec->value_regex_casei)) {
            // notice the use of & as start and end delimiters. Want to avoid use of '/' as it will be in regex, unescaped
            if (isset($attr_spec->value_regex)) {
                $value_regex = '&(*UTF8)^(' . $attr_spec->value_regex . ')$&'; // case sensitive
            } else {
                $value_regex = '&(*UTF8)^(' . $attr_spec->value_regex_casei . ')$&i'; // case insensitive
            }

            // if it _doesn't_ match its an error
            if (!preg_match($value_regex, $encountered_attr_value)) {
                $context->addError(ValidationErrorCode::INVALID_ATTR_VALUE,
                    [$encountered_attr_name, ParsedTagSpec::getTagSpecName($tag_spec), $encountered_attr_value],
                    $tag_spec->spec_url, $result_for_attempt, $encountered_attr_name);
                return false;
            }
        } else if (isset($attr_spec->value_url)) {
            $this->validateAttrValueUrl($context, $encountered_attr_name, $encountered_attr_value,
                $tag_spec, $tag_spec->spec_url, $result_for_attempt);
            if ($result_for_attempt->status === ValidationResultStatus::FAIL) {
                return false;
            }
        } else if (isset($attr_spec->value_properties)) {
            $this->validateAttrValueProperties($context, $encountered_attr_name, $encountered_attr_value,
                $tag_spec, $tag_spec->spec_url, $result_for_attempt);
            if ($result_for_attempt->status === ValidationResultStatus::FAIL) {
                return false;
            }
        }

        return true;
    }
}
