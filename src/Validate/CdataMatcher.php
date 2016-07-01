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

use Lullabot\AMP\Spec\AtRuleSpec;
use Lullabot\AMP\Spec\BlackListedCDataRegex;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\CdataSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\Value\URL;

/**
 * Class CdataMatcher
 * @package Lullabot\AMP\Validate
 *
 * This class is a PHP port of the CdataMatcher class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The main difference between the PHP and Javascript ports is the use the sabberworm/php-css-parser css parser library in
 * the PHP port. The Javascript validator uses its own css parser. This causes some code divergence in areas related
 * to css_spec validation.
 *
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
        /** @var CdataSpec $cdata_spec */
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
        }

        if (!empty($cdata_spec->mandatory_cdata)) {
            if ($cdata !== $cdata_spec->mandatory_cdata) {
                $context->addError(ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT,
                    [ParsedTagSpec::getTagSpecName($this->tag_spec)], $this->tag_spec->spec_url, $result);
            }
            return;
        } else if (!empty($cdata_spec->cdata_regex)) {
            $regex = '&(*UTF8)^(' . $cdata_spec->cdata_regex . ')$&';
            if (!preg_match($regex, $cdata)) {
                $context->addError(ValidationErrorCode::MANDATORY_CDATA_MISSING_OR_INCORRECT,
                    [ParsedTagSpec::getTagSpecName($this->tag_spec)], $this->tag_spec->spec_url, $result);
                return;
            }
        } else if (!empty($cdata_spec->css_spec)) {
            try {
                $this->validateCssSpec($cdata, $context, $result, $cdata_spec);
            } catch (\Exception $e) {
                $context->addError(ValidationErrorCode::CSS_SYNTAX,
                    [ParsedTagSpec::getTagSpecName($this->tag_spec), 'CSS Parser Error: ' . $e->getMessage()], $this->tag_spec->spec_url, $result);
            }
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

    /**
     * @param URL $url
     * @return mixed|string
     */
    protected function url_string(URL $url)
    {
        $possibly_with_quotes = trim($url->getURL()->__toString());
        $matches = [];
        if (empty($possibly_with_quotes)) {
            return '';
        } else if (preg_match('/(*UTF8)^"(.*)"$/', $possibly_with_quotes, $matches) ||
            preg_match('/(*UTF8)^\'(.*)\'$/', $possibly_with_quotes, $matches)
        ) {
            return $matches[1];
        } else {
            return $possibly_with_quotes;
        }
    }

    /**
     * @param string $cdata
     * @param Context $context
     * @param SValidationResult $result
     * @param CdataSpec $cdata_spec
     */
    protected function validateCssSpec($cdata, Context $context, SValidationResult $result, CdataSpec $cdata_spec)
    {
        $parsed_font_url_spec = new ParsedUrlSpec($cdata_spec->css_spec->font_url_spec);
        $parsed_image_url_spec = new ParsedUrlSpec($cdata_spec->css_spec->image_url_spec);

        // We want to start off with line number of the current tag
        $css_parser = new Parser($cdata, null, $context->getLineNo());
        /** @var Document $css_document */
        $css_document = $css_parser->parse();
        /** @var AtRuleSpec $item */
        $at_rule_map = [];
        foreach ($cdata_spec->css_spec->at_rule_spec as $item) {
            $at_rule_map[$item->name] = $item->type;
        }

        foreach ($css_document->getContents() as $rule) {
            $font_face = false;
            if ($rule instanceof AtRule) {
                /** @var AtRuleSet $rule */
                if ($rule->atRuleName() == 'font-face') {
                    $font_face = true;
                }

                if (isset($at_rule_map[$rule->atRuleName()])) {
                    $parse_as = $at_rule_map[$rule->atRuleName()];
                } else {
                    assert(isset($at_rule_map['$DEFAULT']));
                    $parse_as = $at_rule_map['$DEFAULT'];
                }

                if ($parse_as == 'PARSE_AS_ERROR') {
                    $context->addError(ValidationErrorCode::CSS_SYNTAX_INVALID_AT_RULE,
                        [ParsedTagSpec::getTagSpecName($this->tag_spec), $rule->atRuleName()], $this->tag_spec->spec_url, $result, '', '', $rule->getLineNo());
                }
            }

            foreach ($css_document->getAllValues($rule) as $value) {
                if ($value instanceof URL) {
                    /** @var URL $value */
                    if ($font_face) {
                        $parsed_font_url_spec->validateUrlAndProtocolInStyleSheet($context, $this->url_string($value), $this->tag_spec, $result, $value->getLineNo());
                    } /** @var AtRule $rule */
                    else {
                        $parsed_image_url_spec->validateUrlAndProtocolInStyleSheet($context, $this->url_string($value), $this->tag_spec, $result, $value->getLineNo());
                    }
                }
            }
        }
    }
}

