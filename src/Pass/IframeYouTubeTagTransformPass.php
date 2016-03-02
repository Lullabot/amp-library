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
 * Class IframeYouTubeTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all Youtube embed code <iframe> tags which don't have noscript as an ancestor to <amp-youtube> tags
 * This is what a youtube embed looks like:
 *   <iframe width="560" height="315" src="https://www.youtube.com/embed/MnR9AVs6Q_c" frameborder="0" allowfullscreen></iframe>
 * @see https://github.com/ampproject/amphtml/blob/master/extensions/amp-youtube/amp-youtube.md
 *
 */
class IframeYouTubeTagTransformPass extends BasePass
{
    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var \DOMElement $dom_el */
        foreach ($all_iframes->get() as $dom_el) {
            if (!$this->isYouTubeIframe($dom_el)) {
                continue;
            }

            $lineno = $dom_el->getLineNo();
            $context_string = $this->getContextString($dom_el);

            $new_el = $this->renameDomElement($dom_el, 'amp-youtube');
            $this->setAmpYoutubeIframeAttributes($new_el);
            $this->context->addLineAssociation($new_el, $lineno);
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::YOUTUBE_IFRAME_CONVERTED, $lineno, $context_string));
        }

        return $this->warnings;
    }

    protected function isYouTubeIframe(\DOMElement $dom_el)
    {
        if (!$dom_el->hasAttribute('src')) {
            return false;
        }

        $href = $dom_el->getAttribute('src');
        if (preg_match('&(*UTF8)(youtube\.com|youtu\.be)&i', $href)) {
            return true;
        }
    }

    protected function setAmpYoutubeIframeAttributes(\DOMElement $dom_el)
    {
        if (!$dom_el->hasAttribute('layout')) {
            $dom_el->setAttribute('layout', 'responsive');
        }

        if ($dom_el->hasAttribute('frameborder')) {
            $dom_el->removeAttribute('frameborder');
        }

        if ($dom_el->hasAttribute('allowfullscreen')) {
            $dom_el->removeAttribute('allowfullscreen');
        }
    }
}