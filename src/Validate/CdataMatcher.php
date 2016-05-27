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

use Lullabot\AMP\Spec\BlackListedCDataRegex;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\CdataSpec;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\Spec\ValidationErrorCode;

/**
 * Class CdataMatcher
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the Context class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 */
class CdataMatcher
{
    /** @var TagSpec */
    protected $tag_spec;

    public function __construct(TagSpec $tag_spec)
    {
        $this->tag_spec = $tag_spec;
    }

    /**
     * @param string $cdata
     * @param Context $context
     * @param SValidationResult $result
     */
    public function match($cdata, Context $context, SValidationResult $result)
    {
        $cdata_spec = $this->tag_spec->cdata;
        if (empty($cdata_spec)) {
            return;
        }

        /** @var CdataSpec $cdata_spec */
        $max_bytes = $cdata_spec->max_bytes;
        if (!empty($max_bytes)) {
            $num_bytes = strlen($cdata);
            if ($num_bytes > $max_bytes) {
                $context->addError(ValidationErrorCode::STYLESHEET_TOO_LONG,
                    [ParsedTagSpec::getTagSpecName($this->tag_spec), $num_bytes, $max_bytes], $cdata_spec->max_bytes_spec_url, $result);
                return;
            }
        } else if (!empty($cdata_spec->cdata_regex)) {
            $regex = '&(*UTF8)^(' . $cdata_spec->cdata_regex . ')$&';
            if (!preg_match($regex, $cdata)) {
                $context->addError(ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT,
                    [ParsedTagSpec::getTagSpecName($this->tag_spec)], $this->tag_spec->spec_url, $result);
                return;
            }
        } else if (!empty($cdata_spec->css_spec)) {
            // @TODO
        }

        /** @var BlackListedCDataRegex $blackitem */
        foreach ($cdata_spec->blacklisted_cdata_regex as $blackitem) {
            $blackregex = "&(*UTF8)$blackitem->regex&i";
            if (preg_match($blackregex, $cdata)) {
                $context->addError(ValidationErrorCode::CDATA_VIOLATES_BLACKLIST,
                    [ParsedTagSpec::getTagSpecName($this->tag_spec), $blackitem->error_message], $this->tag_spec->spec_url, $result);
            }
        }
    }
}
