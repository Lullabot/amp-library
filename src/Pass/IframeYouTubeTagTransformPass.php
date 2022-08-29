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
 * Class IframeYouTubeTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all Youtube embed code <iframe> tags which don't have noscript as an ancestor to <amp-youtube> tags
 *
 * This is what a youtube embed looks like:
 *   <iframe width="560" height="315" src="https://www.youtube.com/embed/MnR9AVs6Q_c" frameborder="0" allowfullscreen></iframe>
 *
 * @see https://github.com/ampproject/amphtml/blob/main/extensions/amp-youtube/amp-youtube.md
 * @see https://developers.google.com/youtube/iframe_api_reference
 *
 */
class IframeYouTubeTagTransformPass extends BasePass
{
    /**
     * A standard youtube video aspect ratio
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
            if (!$this->isYouTubeIframe($el)) {
                continue;
            }

            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);
            $youtube_code = $this->getYouTubeCode($el);
            $youtube_hash = $this->getYouTubeCodeHash($el);

            // If we couldnt find a youtube videoid then we abort
            if (empty($youtube_code)) {
                continue;
            }

            $extra_data = NULL;
            if (!empty($youtube_hash)) {
                // Try to parse this into a start value:  t=333
                // #t=333 is the old way, and youtube dropped support for it. transform it into start=333
                parse_str($youtube_hash, $extras);
                if (!empty($extras['t'])) {
                    $extra_data = "data-param-start=\"{$extras['t']}\"";
                }
            }

            if ($el->hasAttr('class')) {
                $class_attr = $el->attr('class');
            }

            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-youtube data-videoid=\"$youtube_code\" layout=\"responsive\" {$extra_data}></amp-youtube>");
            $new_el = $el->next();
            $new_dom_el = $new_el->get(0);
            if (!empty($class_attr)) {
                $new_el->attr('class', $class_attr);
            }
            $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_VIDEO_WIDTH, self::DEFAULT_VIDEO_HEIGHT, self::DEFAULT_ASPECT_RATIO);
            $this->setYouTubeAttributesFrom($el, $new_el);

            // Remove the iframe and its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::YOUTUBE_IFRAME_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     * @return bool
     */
    protected function isYouTubeIframe(DOMQuery $el)
    {
        $href = $el->attr('src');
        if (empty($href)) {
            return false;
        }

        if (preg_match('&(*UTF8)(youtube\.com|youtu\.be)&i', $href)) {
            return true;
        }

        return false;
    }

    /**
     *
     * Get the youtube videoid
     *
     * @param DOMQuery $el
     * @return string
     *
     */
    protected function getYouTubeCode(DOMQuery $el)
    {
        $matches = [];
        $youtube_code = '';
        $href = $el->attr('src');

        // This regex is supposed to catch all possible way to embed youtube video
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $href, $matches))
        {
            if (!empty($matches[1]))
            {
                $youtube_code = $matches[1];
            }
        }

        return htmlspecialchars($youtube_code);
    }

    /**
     *
     * Get the youtube anchor hash (if available)
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getYouTubeCodeHash(DOMQuery $el) {
        $href = $el->attr('src');
        // Return hash-tag timestamp jump:  #t=243
        $youtube_code_hash = explode('#', $href);
        return !empty($youtube_code_hash[1]) ? $youtube_code_hash[1] : NULL;
    }

    /**
     * @param DOMQuery $el
     * @param DOMQuery $new_dom_el
     */
    protected function setYouTubeAttributesFrom($el, $new_dom_el)
    {
        $arr = $this->getQueryArray($el);
        // From https://github.com/ampproject/amphtml/blob/main/extensions/amp-youtube/amp-youtube.md
        //  "All data-param-* attributes will be added as query parameter to the youtube iframe src. This may be used to
        //   pass custom values through to youtube plugins, such as autoplay."
        //
        // We're doing this in reverse: we see the query parameters and we make them data-param-*
        foreach ($arr as $query_name => $query_value) {
            $new_dom_el->attr("data-param-$query_name", $query_value);
        }
    }
}
