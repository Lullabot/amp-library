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

namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Spec\AmpLayoutLayout;
use Lullabot\AMP\Validate\GroupedValidationResult;
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Validate\ParsedValidatorRules;
use QueryPath\DOMQuery;
use Lullabot\AMP\Validate\SValidationError;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Validate\ParsedTagSpec;


/**
 * Class AmpImgFixPass
 * @package Lullabot\AMP\Pass
 *
 * Try to fix amp-img tags with bad layouts or responsive layouts in which height and width are not specified properly
 */
class AmpImgFixPass extends ImgTagTransformPass
{
    function __construct(DOMQuery $q, Context $context, SValidationResult $validation_result, GroupedValidationResult $grouped_validation_result, ParsedValidatorRules $parsed_rules, array $options)
    {
        parent::__construct($q, $context, $validation_result, $grouped_validation_result, $parsed_rules, $options);
    }

    function pass()
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if (in_array($error->code, [ValidationErrorCode::INCONSISTENT_UNITS_FOR_WIDTH_AND_HEIGHT, ValidationErrorCode::MANDATORY_ATTR_MISSING, ValidationErrorCode::INVALID_ATTR_VALUE, ValidationErrorCode::IMPLIED_LAYOUT_INVALID, ValidationErrorCode::SPECIFIED_LAYOUT_INVALID]) &&
                !$error->resolved &&
                !empty($error->dom_tag) &&
                strtolower($error->dom_tag->tagName) == 'amp-img'
            ) {
                $amp_img_el = new DOMQuery($error->dom_tag);

                if (in_array($error->code, [ValidationErrorCode::IMPLIED_LAYOUT_INVALID, ValidationErrorCode::SPECIFIED_LAYOUT_INVALID])) {
                    $amp_img_el->attr('layout', 'responsive');
                    $error->addActionTaken(new ActionTakenLine('amp-img', ActionTakenType::AMP_IMG_FIX_RESPONSIVE));
                    $error->resolved = true;
                }

                $layout = ParsedTagSpec::parseLayout($amp_img_el->attr('layout'));
                if (in_array($error->code, [ValidationErrorCode::INCONSISTENT_UNITS_FOR_WIDTH_AND_HEIGHT, ValidationErrorCode::MANDATORY_ATTR_MISSING, ValidationErrorCode::INVALID_ATTR_VALUE]) &&
                    ($layout !== AmpLayoutLayout::RESPONSIVE || !in_array($error->params[0], ['height', 'width', 'amp-img']))
                ) {
                    continue;
                }

                $success = $this->setResponsiveImgHeightAndWidth($amp_img_el);
                if ($success) {
                    $error->addActionTaken(new ActionTakenLine('amp-img', ActionTakenType::AMP_IMG_FIX));
                    $error->resolved = true;
                } else {
                    $error->resolved = false;
                }
            }
        }
    }
}
