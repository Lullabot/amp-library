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
use Lullabot\AMP\Spec\UrlSpec;
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
    public $value_url_allowed_protocols = []; // Set with keys as strings
    /** @var PropertySpec[] */
    public $value_property_by_name = [];
    /** @var PropertySpec[] */
    public $mandatory_value_property_names = [];

    public function __construct(AttrSpec $attr_spec)
    {
        $this->spec = $attr_spec;
        if (!empty($this->spec->value_url)) {
            /** @var UrlSpec $allowed_protocol */
            foreach ($this->spec->value_url->allowed_protocol as $allowed_protocol) {
                $this->value_url_allowed_protocols[$allowed_protocol] = 1; // Treat as a Set

            }
        }

        if (!empty($this->spec->value_properties)) {
            /** @var PropertySpec $property */
            foreach ($this->spec->value_properties->properties as $property) {
                $this->value_property_by_name[$property->name] = $property;
                if ($property->mandatory) {
                    $this->mandatory_value_property_names[$property->name] = $property;
                }
            }
        }
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
     * @param string $url
     * @param TagSpec $tagspec
     * @param string $spec_url
     * @param SValidationResult $validation_result
     */
    public function validateUrlAndProtocol(Context $context, $attr_name, $url, TagSpec $tagspec, $spec_url, SValidationResult $validation_result)
    {
        if (empty(trim($url))) {
            $context->addError(ValidationErrorCode::MISSING_URL,
                [$attr_name, ParsedTagSpec::getDetailOrName($tagspec)], $spec_url, $validation_result, $attr_name);
            return;
        }

        $url_components = parse_url($url);
        if ($url_components === FALSE) {
            $context->addError(ValidationErrorCode::INVALID_URL,
                [$attr_name, ParsedTagSpec::getDetailOrName($tagspec), $url], $spec_url, $validation_result, $attr_name);
            return;
        }

        if (!empty($url_components['scheme'])) {
            $scheme = mb_strtolower($url_components['scheme'], 'UTF-8');
            if (!isset($this->value_url_allowed_protocols[$scheme])) {
                $context->addError(ValidationErrorCode::INVALID_URL_PROTOCOL,
                    [$attr_name, ParsedTagSpec::getDetailOrName($tagspec), $scheme], $spec_url, $validation_result, $attr_name);
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
                [$attr_name, ParsedTagSpec::getDetailOrName($tagspec)], $spec_url, $validation_result, $attr_name);
            return;
        }

        /**
         * @var string $maybe_uri
         * @var number $always_one this is a set, value is always 1
         */
        foreach ($maybe_uris as $maybe_uri => $always_one) {
            $unescape_maybe_uri = html_entity_decode($maybe_uri, ENT_HTML5);
            $this->validateUrlAndProtocol($context, $attr_name, $unescape_maybe_uri, $tagspec, $spec_url, $validation_result);
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
                    [$name, $attr_name, ParsedTagSpec::getDetailOrName($tagspec)], $spec_url, $result, $attr_name, $segment);
                continue;
            }
            /** @var PropertySpec $property_spec */
            $property_spec = $this->value_property_by_name[$name];
            if (!empty($property_spec->value)) {
                if ($property_spec->value != mb_strtolower($value, 'UTF-8')) {
                    $context->addError(ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
                        [$name, $attr_name, ParsedTagSpec::getDetailOrName($tagspec), $value], $spec_url, $result, $attr_name, $segment);
                }
            } else if (!empty($property_spec->value_double)) {
                if (!is_numeric($value) || ((float)$property_spec->value_double) !== ((float)$value)) {
                    $context->addError(ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
                        [$name, $attr_name, ParsedTagSpec::getDetailOrName($tagspec), $value], $spec_url, $result, $attr_name, $segment);
                }
            }
        }

        /** @var PropertySpec $mandatory_value_property_name */
        $names = array_keys($properties);
        foreach ($this->mandatory_value_property_names as $mandatory_value_property_name) {
            if (false === array_search($mandatory_value_property_name->name, $names)) {
                $context->addError(ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE,
                    [$mandatory_value_property_name->name, $attr_name, ParsedTagSpec::getDetailOrName($tagspec)],
                    $spec_url, $result, $attr_name);
            }
        }

    }
}
