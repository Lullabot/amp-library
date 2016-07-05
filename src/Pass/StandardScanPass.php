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

use Lullabot\AMP\Validate\Phase;
use Lullabot\AMP\Validate\RenderValidationResult;
use Lullabot\AMP\Validate\GroupedValidationResult;

/**
 * Class StandardScanPass
 * @package Lullabot\AMP\Pass
 *
 */
class StandardScanPass extends BasePass
{
    public function pass()
    {
        // Set the phase to LOCAL_PHASE before starting out
        $this->context->setPhase(Phase::LOCAL_PHASE);

        // We get back a DOMElements, this is a faster way of iterating over all tags
        // See http://technosophos.com/2009/11/26/iteration-techniques-and-performance-querypath.html
        $all_tags = $this->q->find('*')->get();
        $count = 0;
        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            $count++;
            $this->context->attachDomTag($tag);
            $tagname = mb_strtolower($tag->tagName, 'UTF-8');
            $this->parsed_rules->validateTag($this->context, $tagname, $this->encounteredAttributes($tag), $this->validation_result);
            $this->parsed_rules->validateTagOnExit($this->context, $this->validation_result);
            $this->context->detachDomTag();
        }

        // This will be used by the StatisticsPass
        $this->context->setNumTagsProcessed($count);
        $this->parsed_rules->maybeEmitGlobalTagValidationErrors($this->context, $this->validation_result, $this);
        $this->parsed_rules->endValidation($this->validation_result);

        // Group the errors generated in the scan
        /** @var RenderValidationResult $render_validation_result */
        $render_validation_result = new RenderValidationResult($this->parsed_rules->format_by_code);
        /** @var GroupedValidationResult $grouped_validation_result */
        $render_validation_result->groupValidationResult($this->validation_result, $this->grouped_validation_result);

        return [];
    }
}
