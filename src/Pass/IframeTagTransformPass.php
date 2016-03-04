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

use QueryPath\DOMQuery;

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class IframeTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all <iframe> tags which don't have noscript as an ancestor to <amp-iframe> tags
 */
class IframeTagTransformPass extends BasePass
{
    function pass()
    {
        $all_a = $this->q->find('iframe:not(noscript iframe)');
        /** @var \DOMElement $dom_el */
        foreach ($all_a->get() as $dom_el) {
            $lineno = $dom_el->getLineNo();
            $context_string = $this->getContextString($dom_el);

            $new_el = $this->renameDomElement($dom_el, 'amp-iframe');
            $this->setAmpIframeAttributes($new_el);
            $this->context->addLineAssociation($new_el, $lineno);
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::IFRAME_CONVERTED, $lineno, $context_string));
        }

        return $this->transformations;
    }

    protected function setAmpIframeAttributes(\DOMElement $el)
    {
        // Sane default for now
        if (!$el->hasAttribute('layout')) {
            $el->setAttribute('layout', 'responsive');
        }

        if (!$el->hasAttribute('sandbox')) {
            $el->setAttribute('sandbox', 'allow-scripts allow-same-origin');
        }
    }
}