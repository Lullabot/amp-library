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

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class IframeHuluTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Sample hulu embed code:
 * <iframe width="854" height="480" frameborder="0" allowfullscreen="allowfullscreen" src="//www.hulu.com/embed.html?eid=_hHzwnAcj3RrXMJFDDvkuw"></iframe>
 *
 * @see https://www.ampproject.org/docs/reference/extended/amp-hulu.html
 * @see https://ampbyexample.com/components/amp-hulu/
 */
class IframeHuluTagTransformPass extends BasePass
{
    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $query = $this->getQueryArray($el);
            // If we can't get the videoid, abort
            if (empty($query['eid'])) {
                continue;
            }
            $eid = $query['eid'];
            $width = $el->attr('width') ?: 800;
            $height = $el->attr('height') ?: 600;

            $context_string = $this->getContextString($dom_el);

            // width and height are intended to be aspect ratios here
            $el->after('<amp-hulu width="' . $width . '" height="' . $height . '" layout="responsive" data-eid="' . $eid . '"></amp-hulu>');
            $new_dom_el = $el->next()->get(0);

            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::HULU_CONVERTED, $lineno, $context_string));

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }
}
