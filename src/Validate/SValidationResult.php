<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResult;
use Lullabot\AMP\Spec\ValidationResultStatus;

class SValidationResult extends ValidationResult
{
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

    public static function specificity($code)
    {
        switch ($code) {
            case ValidationErrorCode::UNKNOWN_CODE:
                return 0;
            case ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT:
                return 1;
            case ValidationErrorCode::CDATA_VIOLATES_BLACKLIST:
                return 2;
            case ValidationErrorCode::DISALLOWED_TAG_ANCESTOR:
                return 3;
            case ValidationErrorCode::MANDATORY_TAG_ANCESTOR:
                return 4;
            case ValidationErrorCode::MANDATORY_TAG_ANCESTOR_WITH_HINT:
                return 5;
            case ValidationErrorCode::WRONG_PARENT_TAG:
                return 6;
            case ValidationErrorCode::MANDATORY_TAG_MISSING:
                return 7;
            case ValidationErrorCode::TAG_REQUIRED_BY_MISSING:
                return 8;
            case ValidationErrorCode::DISALLOWED_TAG:
                return 9;
            case ValidationErrorCode::DISALLOWED_ATTR:
                return 10;
            case ValidationErrorCode::INVALID_ATTR_VALUE:
                return 11;
            case ValidationErrorCode::ATTR_VALUE_REQUIRED_BY_LAYOUT:
                return 12;
            case ValidationErrorCode::MANDATORY_ATTR_MISSING:
                return 13;
            case ValidationErrorCode::MANDATORY_ONEOF_ATTR_MISSING:
                return 14;
            case ValidationErrorCode::DUPLICATE_UNIQUE_TAG:
                return 15;
            case ValidationErrorCode::STYLESHEET_TOO_LONG:
                return 16;
            case ValidationErrorCode::CSS_SYNTAX:
                return 17;
            case ValidationErrorCode::CSS_SYNTAX_INVALID_AT_RULE:
                return 18;
            case ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE:
                return 19;
            case ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE:
                return 20;
            case ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE:
                return 21;
            case ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS:
                return 22;
            case ValidationErrorCode::UNESCAPED_TEMPLATE_IN_ATTR_VALUE:
                return 23;
            case ValidationErrorCode::TEMPLATE_PARTIAL_IN_ATTR_VALUE:
                return 24;
            case ValidationErrorCode::TEMPLATE_IN_ATTR_NAME:
                return 25;
            case ValidationErrorCode::INCONSISTENT_UNITS_FOR_WIDTH_AND_HEIGHT:
                return 26;
            case ValidationErrorCode::IMPLIED_LAYOUT_INVALID:
                return 27;
            case ValidationErrorCode::SPECIFIED_LAYOUT_INVALID:
                return 28;
            case ValidationErrorCode::DEV_MODE_ENABLED:
                return 29;
            case ValidationErrorCode::ATTR_DISALLOWED_BY_IMPLIED_LAYOUT:
                return 30;
            case ValidationErrorCode::ATTR_DISALLOWED_BY_SPECIFIED_LAYOUT:
                return 31;
            case ValidationErrorCode::MISSING_URL:
                return 32;
            case ValidationErrorCode::INVALID_URL_PROTOCOL:
                return 33;
            case ValidationErrorCode::INVALID_URL:
                return 34;
            case ValidationErrorCode::DEPRECATED_ATTR:
                return 101;
            case ValidationErrorCode::DEPRECATED_TAG:
                return 102;
            default:
                throw new \Exception('Unknown error code');
        }
    }

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