<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\ErrorCategoryCode;
use Lullabot\AMP\Spec\ErrorFormat;
use Lullabot\AMP\Spec\ValidationError;
use Lullabot\AMP\Spec\ValidationErrorCode;

/**
 * Class RenderValidationResult
 * @package Lullabot\AMP\Validate
 *
 * This class doesn't exist in validator.js (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 * Rather, its a mishmash of some useful functions ported from validator.js into PHP, added to this class.
 *
 */
class RenderValidationResult
{
    /** @var string[] */
    public $format_by_code;

    public function __construct(array $format_by_code)
    {
        $this->format_by_code = $format_by_code;
    }

    /**
     * Corresponds to amp.validator.renderErrorMessage() in validator.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * Ported into this class for convenience as a member function
     *
     * @param ValidationError $validation_error
     * @return string
     */
    protected function renderErrorMessage(ValidationError $validation_error)
    {
        /** @var string $rendered */
        $rendered = '';
        /** @var string $format */
        $format = isset($this->format_by_code[$validation_error->code]) ?
            $this->format_by_code[$validation_error->code] : '';
        if ($format && !empty($validation_error->params)) {
            $rendered .= $this->applyFormat($format, $validation_error);
        } else {
            $rendered .= $validation_error->code;
            if (!empty($validation_error->detail)) {
                $rendered .= $validation_error->detail;
            }
        }

        return $rendered;
    }

    /**
     * Corresponds to top-level function applyFormat() in validator.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * Ported into this class for convenience as a member function
     *
     * @param string $format
     * @param ValidationError $validation_error
     * @return string
     */
    protected function applyFormat($format, ValidationError $validation_error)
    {
        $message = $format;
        foreach ($validation_error->params as $param_index => $param_value_replace_with) {
            $replace_this = $param_index + 1;
            $message = str_replace("%{$replace_this}", $param_value_replace_with, $message);
        }

        return $message;
    }

    /**
     * Corresponds to top-level function errorLine() in validator.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * Ported into this class for convenience as a member function
     *
     * @param ValidationError $validation_error
     * @param string $filename_or_url
     * @param number|string $linenos_width
     * @return string
     */
    public function errorLine(ValidationError $validation_error, $filename_or_url = '', $linenos_width = '')
    {
        $line = empty($validation_error->line) ? 1 : $validation_error->line;
        // We don't have col number unfortunately

        $error_line = sprintf("$filename_or_url:%{$linenos_width}d:", $line);
        $error_line .= $this->renderErrorMessage($validation_error);
        if (!empty($validation_error->spec_url)) {
            $error_line .= " (see {$validation_error->spec_url})";
        }
        if (!empty($validation_error->category)) {
            $error_line .= " [{$validation_error->category}]";
        }
//        if (!empty($validation_error->code)) {
//            $error_line .= " (specific code: {$validation_error->code})";
//        }
        return $error_line;
    }

    /**
     * Corresponds to amp.validator.annotateWithErrorCategories() in validator.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * @param SValidationResult $validation_result
     */
    public function annotateWithErrorCategories(SValidationResult $validation_result)
    {
        /** @var ValidationError $error */
        foreach ($validation_result->errors as $error) {
            $error->category = $this->categorizeError($error);
        }
    }

    /**
     * Corresponds to amp.validator.renderValidationResult() in validator.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * Ported into this class for convenience as a member function
     *
     * @param SValidationResult $validation_result
     * @param string $filename_or_url
     * @return string
     */
    public function renderValidationResult(SValidationResult $validation_result, $filename_or_url = '')
    {
        $this->annotateWithErrorCategories($validation_result);
        /** @var string $rendered */
        $rendered = $validation_result->status . PHP_EOL;
        $linenos_width = strlen((string)count($validation_result->errors));
        /** @var ValidationError $validation_error */
        foreach ($validation_result->errors as $validation_error) {
            $rendered .= $this->errorLine($validation_error, $filename_or_url, $linenos_width) . PHP_EOL;
        }
        return $rendered;
    }

    /**
     * Corresponds to amp.validator.categorizeError() in validator.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
     *
     * @todo currently a partial port. Also does not support templates and cdata/css validation
     *
     * @param ValidationError $error
     * @return ErrorCategoryCode
     */
    public function categorizeError(ValidationError $error)
    {
        if (empty($error->params) ||
            $error->code === ValidationErrorCode::UNKNOWN_CODE ||
            empty($error->code)
        ) {
            return ErrorCategoryCode::UNKNOWN;
        }

        if ($error->code === ValidationErrorCode::DISALLOWED_TAG) {
            if (isset($error->params[0]) &&
                in_array($error->params[0], ['img', 'video', 'audio', 'iframe', 'font'])
            ) {
                return ErrorCategoryCode::DISALLOWED_HTML_WITH_AMP_EQUIVALENT;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::MANDATORY_TAG_ANCESTOR_WITH_HINT) {
            return ErrorCategoryCode::DISALLOWED_HTML_WITH_AMP_EQUIVALENT;
        }

        // @todo check
        if ($error->code === ValidationErrorCode::MANDATORY_TAG_MISSING ||
            ($error->code === ValidationErrorCode::MANDATORY_ATTR_MISSING &&
                isset($error->params[0]) && $error->params[0] === '\\u26a')
        ) {
            return ErrorCategoryCode::MANDATORY_AMP_TAG_MISSING_OR_INCORRECT;
        }

        if (in_array($error->code, [ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE,
                ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
                ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE]) &&
            isset($error->params[2]) && $error->params[2] === 'meta name=viewport'
        ) {
            return ErrorCategoryCode::MANDATORY_AMP_TAG_MISSING_OR_INCORRECT;
        }

        if (($error->code == ValidationErrorCode::INVALID_ATTR_VALUE || $error->code === ValidationErrorCode::MANDATORY_ATTR_MISSING) &&
            isset($error->params[0]) && in_array($error->params[0], ['width', 'height', 'layout'])
        ) {
            return ErrorCategoryCode::AMP_LAYOUT_PROBLEM;
        }

        if (($error->code === ValidationErrorCode::INVALID_ATTR_VALUE) &&
            isset($error->params[0]) && $error->params[0] === 'src' &&
            isset($error->params[1]) && preg_match('/(*UTF8)script$/', $error->params[1])
        ) {
            return ErrorCategoryCode::CUSTOM_JAVASCRIPT_DISALLOWED;
        }

        if (($error->code === ValidationErrorCode::INVALID_ATTR_VALUE) &&
            isset($error->params[1]) && mb_strpos($error->params[1], 'script', 0, 'UTF-8') === 0 &&
            isset($error->params[0]) && $error->params[0] == 'type'
        ) {
            return ErrorCategoryCode::CUSTOM_JAVASCRIPT_DISALLOWED;
        }

        if (in_array($error->code, [ValidationErrorCode::INVALID_ATTR_VALUE,
            ValidationErrorCode::DISALLOWED_ATTR,
            ValidationErrorCode::MANDATORY_ATTR_MISSING])
        ) {
            if (isset($error->params[1]) && mb_strpos($error->params[1], 'amp-', 0, 'UTF-8') === 0) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::MANDATORY_ONEOF_ATTR_MISSING) {
            return ErrorCategoryCode::AMP_TAG_PROBLEM;
        }

        if ($error->code === ValidationErrorCode::DEPRECATED_ATTR ||
            $error->code === ValidationErrorCode::DEPRECATED_TAG
        ) {
            return ErrorCategoryCode::DEPRECATION;
        }

        if ($error->code === ValidationErrorCode::WRONG_PARENT_TAG) {
            if ((isset($error->params[0]) && mb_strpos($error->params[0], 'amp-', 0, 'UTF-8') === 0) ||
                (isset($error->params[1]) && mb_strpos($error->params[1], 'amp-', 0, 'UTF-8') === 0) ||
                (isset($error->params[2]) && mb_strpos($error->params[2], 'amp-', 0, 'UTF-8') === 0)
            ) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::TAG_REQUIRED_BY_MISSING &&
            (isset($error->params[1]) && mb_strpos($error->params[1], 'amp-', 0, 'UTF-8') === 0)
        ) {
            return ErrorCategoryCode::AMP_TAG_PROBLEM;
        }

        if ($error->code === ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS &&
            (isset($error->params[0]) && mb_strpos($error->params[0], 'amp-', 0, 'UTF-8') === 0)
        ) {
            return ErrorCategoryCode::AMP_TAG_PROBLEM;
        }

        if ($error->code === ValidationErrorCode::DUPLICATE_UNIQUE_TAG) {
            return ErrorCategoryCode::MANDATORY_AMP_TAG_MISSING_OR_INCORRECT;
        }

        if ((in_array($error->code, [ValidationErrorCode::MISSING_URL,
            ValidationErrorCode::INVALID_URL,
            ValidationErrorCode::INVALID_URL_PROTOCOL]))
        ) {
            if (isset($error->params[1]) && mb_strpos($error->params[1], 'amp-', 0, 'UTF-8') === 0) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }
            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        return ErrorCategoryCode::GENERIC;
    }

}
