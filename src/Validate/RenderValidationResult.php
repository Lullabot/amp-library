<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\ErrorFormat;
use Lullabot\AMP\Spec\ValidationError;

/**
 * Class RenderValidationResult
 * @package Lullabot\AMP\Validate
 *
 * This class does'nt exist in validator.js (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 * Rather, its a mishmash of some useful functions ported from validator.js into PHP, added to this class,
 * and subsequently customized for our codebase.
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
        if (!empty($validation_error->code)) {
            $error_line .= " [{$validation_error->code}]";
        }
        return $error_line;
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
        /** @var string $rendered */
        $rendered = $validation_result->status . PHP_EOL;
        $linenos_width = strlen((string)count($validation_result->errors));
        /** @var ValidationError $validation_error */
        foreach ($validation_result->errors as $validation_error) {
            $rendered .= $this->errorLine($validation_error, $filename_or_url, $linenos_width) . PHP_EOL;
        }
        return $rendered;
    }

}
