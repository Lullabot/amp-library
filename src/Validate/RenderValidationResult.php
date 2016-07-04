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

use Lullabot\AMP\Spec\ErrorCategoryCode;
use Lullabot\AMP\Spec\ValidationError;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\AMP;

/**
 * Class RenderValidationResult
 * @package Lullabot\AMP\Validate
 *
 * This class does not exist in the canonical validator [1].  Rather, its a mishmash of some useful functions
 * ported from the JavaScript canonical validator into PHP and then agglomerated in this class.
 *
 * [1] See https://github.com/ampproject/amphtml/blob/master/validator/validator.js
 *     Also see https://github.com/ampproject/amphtml/blob/master/validator/validator-full.js
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
     * @param SValidationError $validation_error
     * @param string $filename_or_url
     * @return string
     */
    public function errorLine(SValidationError $validation_error, $filename_or_url = '')
    {
        // We don't have col number unfortunately
        $error_line = '- ' . $this->renderErrorMessage($validation_error);
        if (!empty($validation_error->code)) {
            $error_line .= PHP_EOL . "   [code: {$validation_error->code} ";
        }
        if (!empty($validation_error->category)) {
            $error_line .= " category: {$validation_error->category}";
        }

        if (!empty($validation_error->spec_url)) {
            $error_line .= " see: {$validation_error->spec_url}";
        }

        $error_line .= ']';

        if (!empty($validation_error->action_taken)) {
            $error_line .= PHP_EOL . '   ' . $validation_error->action_taken->human_description;
        }
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
     * The implementation here is different from the one in validator.js. We're doing some grouping.
     *
     * @param GroupedValidationResult $grouped_validation_result
     * @param string $filename_or_url
     * @return string
     */
    public function renderValidationResult(GroupedValidationResult $grouped_validation_result, $filename_or_url = '')
    {
        $rendered = $grouped_validation_result->status . PHP_EOL;
        /** @var GroupedValidationError $group_validation_error */
        foreach ($grouped_validation_result->grouped_validation_errors as $group_validation_error) {
            if ($group_validation_error->context_string == AMP::AMP_GLOBAL_WARNING) {
                $rendered .= PHP_EOL . AMP::AMP_GLOBAL_WARNING . PHP_EOL;
            } else {
                $rendered .= PHP_EOL . $group_validation_error->context_string . " on line $group_validation_error->line" . PHP_EOL;
            }

            foreach ($group_validation_error->validation_errors as $validation_error) {
                $rendered .= $this->errorLine($validation_error, $filename_or_url) . PHP_EOL;
            }

            if (!empty($group_validation_error->action_taken)) {
                // This becomes FINAL ACTION TAKEN
                $rendered .= '- FINAL ' . $group_validation_error->action_taken->human_description . PHP_EOL;
            }
        }
        return $rendered;
    }

    /**
     * @param SValidationResult $validation_result
     * @param string $filename_or_url
     * @param GroupedValidationResult $group_validation_result
     * @return GroupedValidationResult
     */
    public function groupValidationResult(SValidationResult $validation_result, GroupedValidationResult $group_validation_result, $filename_or_url = '')
    {
        $this->annotateWithErrorCategories($validation_result);
        $this->sortValidationWarningsByLineno($validation_result);
        $group_validation_result->status = $validation_result->status;

        /** @var SValidationError $validation_error */
        $group_context_string = null;
        $group_dom_tag = null;
        $group_line_num = -1;
        $group_validation_error = null;
        foreach ($validation_result->errors as $validation_error) {
            if ((!empty($validation_error->dom_tag) &&
                    !empty($group_dom_tag) &&
                    !$validation_error->dom_tag->isSameNode($group_dom_tag)) ||
                ($group_context_string !== $validation_error->context_string) ||
                ($validation_error->line !== $group_line_num)
            ) {
                $group_line_num = $validation_error->line;
                $group_context_string = $validation_error->context_string;
                $group_dom_tag = $validation_error->dom_tag;
                $group_validation_error = new GroupedValidationError($group_context_string, $group_line_num, $group_dom_tag, $validation_error->phase);
                $group_validation_result->grouped_validation_errors[] = $group_validation_error;
            }
            assert($group_context_string == $validation_error->context_string);
            assert($group_validation_error !== null);
            assert($group_line_num == $validation_error->line);
            $group_validation_error->addValidationError($validation_error);
            $group_line_num = $validation_error->line;
        }

        return $group_validation_result;
    }

    protected function sortValidationWarningsByLineno(SValidationResult $result)
    {
        // Sort the warnings according to increasing line number
        usort($result->errors, function (SValidationError $error_1, SValidationError $error_2) {
            if (($error_1->line > $error_2->line)) {
                return 1;
            } else if ($error_1->line < $error_2->line) {
                return -1;
            } else {
                $result = $error_1->time_stamp < $error_2->time_stamp ? -1 : 1;
                return $result;
            }
        });
    }

    /**
     * Corresponds to amp.validator.categorizeError() in validator-full.js
     * (see https://github.com/ampproject/amphtml/blob/master/validator/validator-full.js )
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

        if ($error->code === ValidationErrorCode::MANDATORY_TAG_ANCESTOR) {
            if ((isset($error->params[0]) && strpos($error->params[0], 'amp-') === 0) ||
                (isset($error->params[1]) && strpos($error->params[1], 'amp-') === 0)
            ) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code == ValidationErrorCode::INCORRECT_NUM_CHILD_TAGS) {
            if (isset($error->params[0]) && strpos($error->params[0], 'amp-') === 0) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::DISALLOWED_CHILD_TAG_NAME ||
            $error->code === ValidationErrorCode::DISALLOWED_FIRST_CHILD_TAG_NAME
        ) {
            if ((isset($error->params[0]) && strpos($error->params[0], 'amp-') === 0) ||
                (isset($error->params[1]) && strpos($error->params[1], 'amp-') === 0)
            ) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::STYLESHEET_TOO_LONG ||
            ($error->code === ValidationErrorCode::CDATA_VIOLATES_BLACKLIST &&
                isset($error->params[0]) && $error->params[0] === 'style amp-custom')
        ) {
            return ErrorCategoryCode::AUTHOR_STYLESHEET_PROBLEM;
        }

        if ($error->code === ValidationErrorCode::CSS_SYNTAX &&
            isset($error->params[0]) && $error->params[0] === 'style amp-custom'
        ) {
            return ErrorCategoryCode::AUTHOR_STYLESHEET_PROBLEM;
        }

        if (($error->code ===
                ValidationErrorCode::CSS_SYNTAX_STRAY_TRAILING_BACKSLASH ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_UNTERMINATED_COMMENT ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_UNTERMINATED_STRING ||
                $error->code === ValidationErrorCode::CSS_SYNTAX_BAD_URL ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_EOF_IN_PRELUDE_OF_QUALIFIED_RULE ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_INVALID_DECLARATION ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_INCOMPLETE_DECLARATION ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_INVALID_AT_RULE ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_ERROR_IN_PSEUDO_SELECTOR ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_MISSING_SELECTOR ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_NOT_A_SELECTOR_START ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_UNPARSED_INPUT_REMAINS_IN_SELECTOR ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_MISSING_URL ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_INVALID_URL ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_INVALID_URL_PROTOCOL ||
                $error->code ===
                ValidationErrorCode::CSS_SYNTAX_DISALLOWED_RELATIVE_URL) &&
            isset($error->params[0]) && $error->params[0] === 'style amp-custom'
        ) {
            return ErrorCategoryCode::AUTHOR_STYLESHEET_PROBLEM;
        }

        // @todo check
        if ($error->code === ValidationErrorCode::MANDATORY_TAG_MISSING ||
            ($error->code === ValidationErrorCode::MANDATORY_ATTR_MISSING &&
                isset($error->params[0]) && $error->params[0] === '\\u26a') ||
            ($error->code === ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT
                && isset($error->params[0]) && ((strpos($error->params[0], 'head > style[amp-boilerplate]') === 0) ||
                    (strpos($error->params[0], 'noscript > style[amp-boilerplate]') === 0)))
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

        if (in_array($error->code, [ValidationErrorCode::ATTR_VALUE_REQUIRED_BY_LAYOUT, ValidationErrorCode::IMPLIED_LAYOUT_INVALID,
                ValidationErrorCode::SPECIFIED_LAYOUT_INVALID]) ||
            ($error->code === ValidationErrorCode::INCONSISTENT_UNITS_FOR_WIDTH_AND_HEIGHT ||
                (($error->code === ValidationErrorCode::INVALID_ATTR_VALUE || $error->code === ValidationErrorCode::MANDATORY_ATTR_MISSING) &&
                    (isset($error->params[0]) && in_array($error->params[0], ['width', 'height', 'layout']))))
        ) {
            return ErrorCategoryCode::AMP_LAYOUT_PROBLEM;
        }

        if (in_array($error->code, [ValidationErrorCode::ATTR_DISALLOWED_BY_IMPLIED_LAYOUT,
            ValidationErrorCode::ATTR_DISALLOWED_BY_SPECIFIED_LAYOUT])) {
            return ErrorCategoryCode::AMP_LAYOUT_PROBLEM;
        }

        if (($error->code === ValidationErrorCode::INVALID_ATTR_VALUE) &&
            isset($error->params[0]) && $error->params[0] === 'src' &&
            isset($error->params[1]) && preg_match('/(*UTF8)script$/', $error->params[1])
        ) {
            return ErrorCategoryCode::CUSTOM_JAVASCRIPT_DISALLOWED;
        }

        if ($error->code === ValidationErrorCode::GENERAL_DISALLOWED_TAG &&
            isset($error->params[0]) && $error->params[0] === 'script'
        ) {
            return ErrorCategoryCode::CUSTOM_JAVASCRIPT_DISALLOWED;
        }

        if (($error->code === ValidationErrorCode::INVALID_ATTR_VALUE) &&
            isset($error->params[1]) && strpos($error->params[1], 'script') === 0 &&
            isset($error->params[0]) && $error->params[0] === 'type'
        ) {
            return ErrorCategoryCode::CUSTOM_JAVASCRIPT_DISALLOWED;
        }

        if (in_array($error->code, [ValidationErrorCode::INVALID_ATTR_VALUE,
            ValidationErrorCode::DISALLOWED_ATTR,
            ValidationErrorCode::MANDATORY_ATTR_MISSING])
        ) {
            if (isset($error->params[1]) && strpos($error->params[1], 'amp-') === 0) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            if (isset($error->params[1]) && strpos($error->params[1], 'on') === 0) {
                return ErrorCategoryCode::CUSTOM_JAVASCRIPT_DISALLOWED;
            }

            if (isset($error->params[1]) && ($error->params[1] === "style" || $error->params[1] === 'link rel=stylesheet for fonts')) {
                return ErrorCategoryCode::AUTHOR_STYLESHEET_PROBLEM;
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
            if ((isset($error->params[0]) && strpos($error->params[0], 'amp-') === 0) ||
                (isset($error->params[1]) && strpos($error->params[1], 'amp-') === 0) ||
                (isset($error->params[2]) && strpos($error->params[2], 'amp-') === 0)
            ) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }

            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::TAG_REQUIRED_BY_MISSING &&
            (isset($error->params[1]) && (
                    (strpos($error->params[1], 'amp-') === 0) ||
                    $error->params[1] === 'template'
                ))
        ) {
            return ErrorCategoryCode::AMP_TAG_PROBLEM;
        }

        if ($error->code === ValidationErrorCode::ATTR_REQUIRED_BUT_MISSING) {
            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code === ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS &&
            (isset($error->params[0]) && strpos($error->params[0], 'amp-') === 0)
        ) {
            return ErrorCategoryCode::AMP_TAG_PROBLEM;
        }

        if ($error->code === ValidationErrorCode::DUPLICATE_UNIQUE_TAG) {
            return ErrorCategoryCode::MANDATORY_AMP_TAG_MISSING_OR_INCORRECT;
        }

        if (in_array($error->code, [ValidationErrorCode::UNESCAPED_TEMPLATE_IN_ATTR_VALUE,
            ValidationErrorCode::TEMPLATE_PARTIAL_IN_ATTR_VALUE,
            ValidationErrorCode::TEMPLATE_IN_ATTR_NAME])) {
            return ErrorCategoryCode::AMP_HTML_TEMPLATE_PROBLEM;
        }

        if ($error->code ===
            ValidationErrorCode::DISALLOWED_TAG_ANCESTOR &&
            (isset($error->params[1]) && strpos($error->params[1], 'amp-') === 0)
        ) {
            return ErrorCategoryCode::AMP_TAG_PROBLEM;
        }

        if ($error->code ===
            ValidationErrorCode::DISALLOWED_TAG_ANCESTOR &&
            (isset($error->params[1]) && $error->params[1] === 'template')
        ) {
            return ErrorCategoryCode::AMP_HTML_TEMPLATE_PROBLEM;
        }

        if ((in_array($error->code, [ValidationErrorCode::MISSING_URL,
            ValidationErrorCode::INVALID_URL,
            ValidationErrorCode::INVALID_URL_PROTOCOL,
            ValidationErrorCode::DISALLOWED_RELATIVE_URL]))
        ) {
            if (isset($error->params[1]) && strpos($error->params[1], 'amp-') === 0) {
                return ErrorCategoryCode::AMP_TAG_PROBLEM;
            }
            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        if ($error->code == ValidationErrorCode::DUPLICATE_DIMENSION) {
            return ErrorCategoryCode::DISALLOWED_HTML;
        }

        return ErrorCategoryCode::GENERIC;
    }

}
