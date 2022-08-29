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


use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;

/**
 * Class ParsedUrlSpecStyleSheetErrorAdapter
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedUrlSpec.StyleSheetErrorAdapter class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/main/validator/validator.js )
 */
class ParsedUrlSpecStyleSheetErrorAdapter
{
    /**
     * @param Context $context
     * @param TagSpec $tagspec
     * @param SValidationResult $result
     * @param int $line_delta
     */
    public function missingUrl(Context $context, TagSpec $tagspec, SValidationResult $result, $line_delta = 0)
    {
        $context->addError(ValidationErrorCode::CSS_SYNTAX_MISSING_URL,
            [ParsedTagSpec::getTagSpecName($tagspec)], $tagspec->spec_url, $result, '', '', $line_delta);
    }

    /**
     * @param Context $context
     * @param string $url
     * @param TagSpec $tagspec
     * @param SValidationResult $result
     * @param int $line_delta
     */
    public function invalidUrl(Context $context, $url, TagSpec $tagspec, SValidationResult $result, $line_delta = 0)
    {
        $context->addError(ValidationErrorCode::CSS_SYNTAX_INVALID_URL,
            [ParsedTagSpec::getTagSpecName($tagspec), $url], $tagspec->spec_url, $result, '', '', $line_delta);
    }

    /**
     * @param Context $context
     * @param string $uri_scheme
     * @param TagSpec $tagspec
     * @param SValidationResult $result
     * @param int $line_delta
     */
    public function invalidUrlProtocol(Context $context, $uri_scheme, TagSpec $tagspec, SValidationResult $result, $line_delta = 0)
    {
        $context->addError(ValidationErrorCode::CSS_SYNTAX_INVALID_URL_PROTOCOL,
            [ParsedTagSpec::getTagSpecName($tagspec), $uri_scheme], $tagspec->spec_url, $result, '', '', $line_delta);
    }

    /**
     * @param Context $context
     * @param $url
     * @param TagSpec $tagspec
     * @param $result
     * @param int $line_delta
     */
    public function disallowedRelativeUrl(Context $context, $url, TagSpec $tagspec, $result, $line_delta = 0)
    {
        $context->addError(ValidationErrorCode::CSS_SYNTAX_DISALLOWED_RELATIVE_URL,
            [ParsedTagSpec::getTagSpecName($tagspec), $url], $tagspec->spec_url, $result, '', '', $line_delta);
    }
}
