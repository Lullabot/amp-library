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

use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResult;
use Lullabot\AMP\Spec\ValidationResultStatus;

/**
 * Class SValidationResult
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ValidationResult class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * Some additional functions from validator.js, from outside the ValidationResult class have also been incorporated
 * into this class for convenience when they were ported. See maxSpecificity(), specificity().
 *
 * Please note that ValidationResult class is already available from Lullabot\AMP\Spec\ValidationResult so this class
 * has been subclassed from that. The "S" in "SValidationResult" has no meaning other than to convey it is a subclass.
 *
 * Use/instantiate the SValidationResult class, don't use/instantiate the ValidationResult class unless you know what
 * you're doing.
 *
 */
class SValidationResult extends ValidationResult
{
    /**
     * Corresponds to maxSpecificity() top level function in validator.js.
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * Ported into this class for convenience as a static member function
     *
     * @param SValidationResult $validation_result
     * @return int
     * @throws \Exception
     */
    public static function maxSpecificity(SValidationResult $validation_result)
    {
        $max = 0;
        foreach ($validation_result->errors as $error) {
            assert(!empty($error->code));
            $specificity = self::specificity($error->code);
            if ($max < $specificity) {
                $max = $specificity;
            }
        }

        return $max;
    }

    /**
     * Corresponds specificity() top level function in validator.js.
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * Ported into this class for convenience as a static member function
     *
     * @param $code
     * @return int
     * @throws \Exception
     */
    public static function specificity($code)
    {
        switch ($code) {
            case ValidationErrorCode::UNKNOWN_CODE:
              return 0;
            case ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT:
              return 1;
            case ValidationErrorCode::CDATA_VIOLATES_BLACKLIST:
              return 2;
            case ValidationErrorCode::NON_WHITESPACE_CDATA_ENCOUNTERED:
              return 3;
            case ValidationErrorCode::INVALID_JSON_CDATA:
              return 4;
            case ValidationErrorCode::DISALLOWED_TAG_ANCESTOR:
              return 5;
            case ValidationErrorCode::MANDATORY_TAG_ANCESTOR:
              return 6;
            case ValidationErrorCode::MANDATORY_TAG_ANCESTOR_WITH_HINT:
              return 7;
            case ValidationErrorCode::MANDATORY_TAG_MISSING:
              return 8;
            case ValidationErrorCode::WRONG_PARENT_TAG:
              return 9;
            case ValidationErrorCode::TAG_REQUIRED_BY_MISSING:
              return 10;
            case ValidationErrorCode::TAG_EXCLUDED_BY_TAG:
              return 11;
            case ValidationErrorCode::MISSING_REQUIRED_EXTENSION:
              return 12;
            case ValidationErrorCode::ATTR_MISSING_REQUIRED_EXTENSION:
              return 13;
            case ValidationErrorCode::WARNING_TAG_REQUIRED_BY_MISSING:
              return 14;
            case ValidationErrorCode::EXTENSION_UNUSED:
              return 15;
            case ValidationErrorCode::WARNING_EXTENSION_UNUSED:
              return 16;
            case ValidationErrorCode::WARNING_EXTENSION_DEPRECATED_VERSION:
              return 17;
            case ValidationErrorCode::DISALLOWED_TAG:
              return 18;
            case ValidationErrorCode::DISALLOWED_ATTR:
              return 19;
            case ValidationErrorCode::INVALID_ATTR_VALUE:
              return 20;
            case ValidationErrorCode::DUPLICATE_ATTRIBUTE:
              return 21;
            case ValidationErrorCode::ATTR_VALUE_REQUIRED_BY_LAYOUT:
              return 22;
            case ValidationErrorCode::MANDATORY_ATTR_MISSING:
              return 23;
            case ValidationErrorCode::MANDATORY_ONEOF_ATTR_MISSING:
              return 24;
            case ValidationErrorCode::MANDATORY_ANYOF_ATTR_MISSING:
              return 25;
            case ValidationErrorCode::ATTR_REQUIRED_BUT_MISSING:
              return 26;
            case ValidationErrorCode::DUPLICATE_UNIQUE_TAG:
              return 27;
            case ValidationErrorCode::DUPLICATE_UNIQUE_TAG_WARNING:
              return 28;
            case ValidationErrorCode::STYLESHEET_TOO_LONG:
              return 29;
            case ValidationErrorCode::STYLESHEET_AND_INLINE_STYLE_TOO_LONG:
              return 30;
            case ValidationErrorCode::INLINE_STYLE_TOO_LONG:
              return 31;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_AT_RULE:
              return 32;
            case ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE:
              return 33;
            case ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE:
              return 34;
            case ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE:
              return 35;
            case ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS:
              return 36;
            case ValidationErrorCode::UNESCAPED_TEMPLATE_IN_ATTR_VALUE:
              return 37;
            case ValidationErrorCode::TEMPLATE_PARTIAL_IN_ATTR_VALUE:
              return 38;
            case ValidationErrorCode::TEMPLATE_IN_ATTR_NAME:
              return 39;
            case ValidationErrorCode::INCONSISTENT_UNITS_FOR_WIDTH_AND_HEIGHT:
              return 40;
            case ValidationErrorCode::MISSING_LAYOUT_ATTRIBUTES:
              return 41;
            case ValidationErrorCode::IMPLIED_LAYOUT_INVALID:
              return 42;
            case ValidationErrorCode::SPECIFIED_LAYOUT_INVALID:
              return 43;
            case ValidationErrorCode::ATTR_DISALLOWED_BY_IMPLIED_LAYOUT:
              return 44;
            case ValidationErrorCode::ATTR_DISALLOWED_BY_SPECIFIED_LAYOUT:
              return 45;
            case ValidationErrorCode::DUPLICATE_DIMENSION:
              return 46;
            case ValidationErrorCode::DISALLOWED_RELATIVE_URL:
              return 47;
            case ValidationErrorCode::MISSING_URL:
              return 48;
            case ValidationErrorCode::DISALLOWED_DOMAIN:
              return 49;
            case ValidationErrorCode::INVALID_URL_PROTOCOL:
              return 50;
            case ValidationErrorCode::INVALID_URL:
              return 51;
            case ValidationErrorCode::DISALLOWED_STYLE_ATTR:
              return 52;
            case ValidationErrorCode::CSS_SYNTAX_STRAY_TRAILING_BACKSLASH:
              return 53;
            case ValidationErrorCode::CSS_SYNTAX_UNTERMINATED_COMMENT:
              return 54;
            case ValidationErrorCode::CSS_SYNTAX_UNTERMINATED_STRING:
              return 55;
            case ValidationErrorCode::CSS_SYNTAX_BAD_URL:
              return 56;
            case ValidationErrorCode::CSS_SYNTAX_EOF_IN_PRELUDE_OF_QUALIFIED_RULE:
              return 57;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_DECLARATION:
              return 58;
            case ValidationErrorCode::CSS_SYNTAX_INCOMPLETE_DECLARATION:
              return 59;
            case ValidationErrorCode::CSS_SYNTAX_ERROR_IN_PSEUDO_SELECTOR:
              return 60;
            case ValidationErrorCode::CSS_SYNTAX_MISSING_SELECTOR:
              return 61;
            case ValidationErrorCode::CSS_SYNTAX_NOT_A_SELECTOR_START:
              return 62;
            case ValidationErrorCode::CSS_SYNTAX_UNPARSED_INPUT_REMAINS_IN_SELECTOR:
              return 63;
            case ValidationErrorCode::CSS_SYNTAX_MISSING_URL:
              return 64;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_DOMAIN:
              return 65;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_URL:
              return 66;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_URL_PROTOCOL:
              return 67;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_RELATIVE_URL:
              return 68;
            case ValidationErrorCode::INCORRECT_NUM_CHILD_TAGS:
              return 69;
            case ValidationErrorCode::DISALLOWED_CHILD_TAG_NAME:
              return 70;
            case ValidationErrorCode::DISALLOWED_FIRST_CHILD_TAG_NAME:
              return 71;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_ATTR_SELECTOR:
              return 72;
            case ValidationErrorCode::CHILD_TAG_DOES_NOT_SATISFY_REFERENCE_POINT:
              return 73;
            case ValidationErrorCode::MANDATORY_REFERENCE_POINT_MISSING:
              return 74;
            case ValidationErrorCode::DUPLICATE_REFERENCE_POINT:
              return 75;
            case ValidationErrorCode::TAG_REFERENCE_POINT_CONFLICT:
              return 76;
            case ValidationErrorCode::CHILD_TAG_DOES_NOT_SATISFY_REFERENCE_POINT_SINGULAR:
              return 77;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_PROPERTY_VALUE:
              return 78;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_PROPERTY_VALUE_WITH_HINT:
              return 79;
            case ValidationErrorCode::CSS_SYNTAX_PROPERTY_DISALLOWED_WITHIN_AT_RULE:
              return 80;
            case ValidationErrorCode::CSS_SYNTAX_PROPERTY_DISALLOWED_TOGETHER_WITH:
              return 81;
            case ValidationErrorCode::CSS_SYNTAX_PROPERTY_REQUIRES_QUALIFICATION:
              return 82;
            case ValidationErrorCode::BASE_TAG_MUST_PRECEED_ALL_URLS:
              return 83;
            case ValidationErrorCode::DISALLOWED_SCRIPT_TAG:
              return 100;
            case ValidationErrorCode::GENERAL_DISALLOWED_TAG:
              return 101;
            case ValidationErrorCode::DEPRECATED_ATTR:
              return 102;
            case ValidationErrorCode::DEPRECATED_TAG:
              return 103;
            case ValidationErrorCode::DISALLOWED_MANUFACTURED_BODY:
              return 104;
            case ValidationErrorCode::DOCUMENT_TOO_COMPLEX:
              return 105;
            case ValidationErrorCode::INCORRECT_MIN_NUM_CHILD_TAGS:
              return 106;
            case ValidationErrorCode::TAG_NOT_ALLOWED_TO_HAVE_SIBLINGS:
              return 107;
            case ValidationErrorCode::MANDATORY_LAST_CHILD_TAG:
              return 108;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_PROPERTY:
              return 109;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_PROPERTY_NOLIST:
              return 110;
            case ValidationErrorCode::CSS_SYNTAX_QUALIFIED_RULE_HAS_NO_DECLARATIONS:
              return 111;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_QUALIFIED_RULE_MUST_BE_INSIDE_KEYFRAME:
              return 112;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_KEYFRAME_INSIDE_KEYFRAME:
              return 113;
            case ValidationErrorCode::CSS_SYNTAX_MALFORMED_MEDIA_QUERY:
              return 115;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_MEDIA_TYPE:
              return 116;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_MEDIA_FEATURE:
              return 117;
            case ValidationErrorCode::INVALID_UTF8:
              return 118;
            default:
                throw new \Exception('Unknown error code');
        }
    }

    /**
     * @param SValidationResult $other
     */
    public function mergeFrom(SValidationResult $other)
    {
        assert(!empty($this->status));
        assert(!empty($other->status));
        if ($other->status !== ValidationResultStatus::UNKNOWN) {
            $this->status = $other->status;
        }

        foreach ($other->errors as $error) {
            $this->errors[] = $error;
        }
    }

}