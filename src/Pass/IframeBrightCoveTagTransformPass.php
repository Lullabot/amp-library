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
 * Class IframeBrighCoveTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all BrighCove embed code <iframe> tags which don't have noscript as an ancestor to <amp-brighcove> tags
 *
 * This is what a BrighCove embed looks like:
 *   <iframe width="560" height="315" src="https://www.BrighCove.com/embed/MnR9AVs6Q_c" frameborder="0" allowfullscreen></iframe>
 *
 * @see https://github.com/ampproject/amphtml/blob/master/extensions/amp-BrighCove/amp-BrighCove.md
 * @see https://developers.google.com/BrighCove/iframe_api_reference
 *
 */
class IframeBrighCoveTagTransformPass extends BasePass
{
    /**
     * A standard BrighCove video aspect ratio
     * @var float
     */
    const DEFAULT_ASPECT_RATIO = 1.7778;
    const DEFAULT_VIDEO_WIDTH = 560;
    const DEFAULT_VIDEO_HEIGHT = 315;

    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            if (!$this->isBrighCoveIframe($el)) {
                continue;
            }

            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);
            $brightcove_code = $this->getBrightcoveCode($el);

            // If we couldnt find a BrighCove videoid then we abort
            if (empty($brightcove_code)) {
                continue;
            }

            if ($el->hasAttr('class')) {
                $class_attr = $el->attr('class');
            }

            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-brightcove data-videoid=\"$brightcove_code\" layout=\"responsive\"></amp-brightcove>");
            $new_el = $el->next();
            $new_dom_el = $new_el->get(0);
            if (!empty($class_attr)) {
                $new_el->attr('class', $class_attr);
            }
            $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_VIDEO_WIDTH, self::DEFAULT_VIDEO_HEIGHT, self::DEFAULT_ASPECT_RATIO);
            $this->setBrightcoveAttributesFrom($el, $new_el);

            // Remove the iframe and its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::BRIGHTCOVE_IFRAME_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     * @return bool
     */
    protected function isBrighCoveIframe(DOMQuery $el)
    {
        $href = $el->attr('src');
        if (empty($href)) {
            return false;
        }

        if (preg_match('&(*UTF8)(brighcove\.com|youtu\.be)&i', $href)) {
            return true;
        }

        return false;
    }

    /**
     *
     * Get the brightcove videoid
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getBrightcoveCode(DOMQuery $el)
    {
        $matches = [];
        $brightcove_code = '';
        $href = $el->attr('src');

        // @todo there seem to be a lot of ways to embed a brightcove video. We probably need to capture all patterns here
        // The next one is the embed code that brightcove gives you
        if (preg_match('&(*UTF8)/embed/([^/?]+)&i', $href, $matches)) {
            if (!empty($matches[1])) {
                $brightcove_code = $matches[1];
            }
        }
        elseif (preg_match('&(*UTF8)brightcove/([^/?]+)&i', $href, $matches)) {
            if (!empty($matches[1])) {
                $brightcove_code = $matches[1];
            }
        }
        elseif (preg_match('!(*UTF8)watch\?v=([^&]+)!i', $href, $matches)) {
            if (!empty($matches[1])) {
                $brightcove_code = $matches[1];
            }
        }

        return $brightcove_code;
    }

    /**
     * @param DOMQuery $el
     * @param DOMQuery $new_dom_el
     */
    protected function setBrightcoveAttributesFrom($el, $new_dom_el)
    {
        $arr = $this->getQueryArray($el);
        // From https://github.com/ampproject/amphtml/blob/master/extensions/amp-brightcove/amp-brightcove.md
        //  "All data-param-* attributes will be added as query parameter to the brightcove iframe src. This may be used to
        //   pass custom values through to brightcove plugins, such as autoplay."
        //
        // We're doing this in reverse: we see the query parameters and we make them data-param-*
        foreach ($arr as $query_name => $query_value) {
            $new_dom_el->attr("data-param-$query_name", $query_value);
        }
    }
}
