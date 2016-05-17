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
            case ValidationErrorCode::WRONG_PARENT_TAG:
                return 3;
            case ValidationErrorCode::DISALLOWED_TAG_ANCESTOR:
                return 4;
            case ValidationErrorCode::MANDATORY_TAG_ANCESTOR:
                return 5;
            case ValidationErrorCode::MANDATORY_TAG_ANCESTOR_WITH_HINT:
                return 6;
            case ValidationErrorCode::MANDATORY_TAG_MISSING:
                return 7;
            case ValidationErrorCode::TAG_REQUIRED_BY_MISSING:
                return 8;
            case ValidationErrorCode::ATTR_REQUIRED_BUT_MISSING:
                return 9;
            case ValidationErrorCode::DISALLOWED_TAG:
                return 10;
            case ValidationErrorCode::DISALLOWED_ATTR:
                return 11;
            case ValidationErrorCode::INVALID_ATTR_VALUE:
                return 12;
            case ValidationErrorCode::ATTR_VALUE_REQUIRED_BY_LAYOUT:
                return 13;
            case ValidationErrorCode::MANDATORY_ATTR_MISSING:
                return 14;
            case ValidationErrorCode::MANDATORY_ONEOF_ATTR_MISSING:
                return 15;
            case ValidationErrorCode::DUPLICATE_UNIQUE_TAG:
                return 16;
            case ValidationErrorCode::STYLESHEET_TOO_LONG_OLD_VARIANT:
                return 17;
            case ValidationErrorCode::STYLESHEET_TOO_LONG:
                return 18;
            case ValidationErrorCode::CSS_SYNTAX:
                return 19;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_AT_RULE:
                return 20;
            case ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE:
                return 21;
            case ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE:
                return 22;
            case ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE:
                return 23;
            case ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS:
                return 24;
            case ValidationErrorCode::UNESCAPED_TEMPLATE_IN_ATTR_VALUE:
                return 25;
            case ValidationErrorCode::TEMPLATE_PARTIAL_IN_ATTR_VALUE:
                return 26;
            case ValidationErrorCode::TEMPLATE_IN_ATTR_NAME:
                return 27;
            case ValidationErrorCode::INCONSISTENT_UNITS_FOR_WIDTH_AND_HEIGHT:
                return 28;
            case ValidationErrorCode::IMPLIED_LAYOUT_INVALID:
                return 29;
            case ValidationErrorCode::SPECIFIED_LAYOUT_INVALID:
                return 30;
            case ValidationErrorCode::DEV_MODE_ENABLED:
                return 31;
            case ValidationErrorCode::ATTR_DISALLOWED_BY_IMPLIED_LAYOUT:
                return 32;
            case ValidationErrorCode::ATTR_DISALLOWED_BY_SPECIFIED_LAYOUT:
                return 33;
            case ValidationErrorCode::DUPLICATE_DIMENSION:
                return 34;
            case ValidationErrorCode::DISALLOWED_RELATIVE_URL:
                return 35;
            case ValidationErrorCode::MISSING_URL:
                return 36;
            case ValidationErrorCode::INVALID_URL_PROTOCOL:
                return 37;
            case ValidationErrorCode::INVALID_URL:
                return 38;
            case ValidationErrorCode::CSS_SYNTAX_STRAY_TRAILING_BACKSLASH:
                return 39;
            case ValidationErrorCode::CSS_SYNTAX_UNTERMINATED_COMMENT:
                return 40;
            case ValidationErrorCode::CSS_SYNTAX_UNTERMINATED_STRING:
                return 41;
            case ValidationErrorCode::CSS_SYNTAX_BAD_URL:
                return 42;
            case ValidationErrorCode::CSS_SYNTAX_EOF_IN_PRELUDE_OF_QUALIFIED_RULE:
                return 43;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_DECLARATION:
                return 44;
            case ValidationErrorCode::CSS_SYNTAX_INCOMPLETE_DECLARATION:
                return 45;
            case ValidationErrorCode::CSS_SYNTAX_ERROR_IN_PSEUDO_SELECTOR:
                return 46;
            case ValidationErrorCode::CSS_SYNTAX_MISSING_SELECTOR:
                return 47;
            case ValidationErrorCode::CSS_SYNTAX_NOT_A_SELECTOR_START:
                return 48;
            case ValidationErrorCode::CSS_SYNTAX_UNPARSED_INPUT_REMAINS_IN_SELECTOR:
                return 49;
            case ValidationErrorCode::CSS_SYNTAX_MISSING_URL:
                return 50;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_URL:
                return 51;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_URL_PROTOCOL:
                return 52;
            case ValidationErrorCode::CSS_SYNTAX_DISALLOWED_RELATIVE_URL:
                return 53;
            case ValidationErrorCode::INCORRECT_NUM_CHILD_TAGS:
                return 54;
            case ValidationErrorCode::DISALLOWED_CHILD_TAG_NAME:
                return 55;
            case ValidationErrorCode::DISALLOWED_FIRST_CHILD_TAG_NAME:
                return 56;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_ATTR_SELECTOR:
                return 57;
            case ValidationErrorCode::GENERAL_DISALLOWED_TAG:
                return 100;
            case ValidationErrorCode::DEPRECATED_ATTR:
                return 101;
            case ValidationErrorCode::DEPRECATED_TAG:
                return 102;
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