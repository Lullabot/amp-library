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
 * Class IframeVimeoTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Sample vimeo embed code:
 * <iframe src="https://player.vimeo.com/video/18352872" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
 * <p><a href="https://vimeo.com/18352872">Drupal 7 Marketing Video</a> from <a href="https://vimeo.com/lullabot">Lullabot</a> on <a href="https://vimeo.com">Vimeo</a>.</p>
 *
 * @see https://www.ampproject.org/docs/reference/extended/amp-vimeo.html
 * @see https://ampbyexample.com/components/amp-vimeo/
 */
class IframeVimeoTagTransformPass extends BasePass
{
    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $videoid = $this->getArtifactId($el, '&(*UTF8)vimeo\.com/video/(\d+)&i');
            // If we can't get the videoid, abort
            if (empty($videoid)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);

            // width and height are intended to be aspect ratios here
            $el->after('<amp-vimeo width="16" height="9" layout="responsive" data-videoid="' . $videoid . '"></amp-vimeo>');
            $new_dom_el = $el->next()->get(0);

            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::VIMEO_CONVERTED, $lineno, $context_string));

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }
}
