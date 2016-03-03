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
 *
 * This is what a youtube embed looks like:
 *   <iframe width="560" height="315" src="https://www.youtube.com/embed/MnR9AVs6Q_c" frameborder="0" allowfullscreen></iframe>
 *
 * @see https://github.com/ampproject/amphtml/blob/master/extensions/amp-youtube/amp-youtube.md
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

            $lineno = $dom_el->getLineNo();
            $context_string = $this->getContextString($dom_el);
            $youtube_code = $this->getYouTubeCode($el);

            // If we couldnt find a youtube videoid then we abort
            if (empty($youtube_code)) {
                continue;
            }

            $youtube_attributes = $this->getYouTubeAttributes($el);

            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-youtube $youtube_attributes data-videoid=\"$youtube_code\" layout=\"responsive\"></amp-youtube>");
            $new_dom_el = $el->get(0);

            // Remove the iframe and its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::YOUTUBE_IFRAME_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->warnings;
    }

    protected function isYouTubeIframe(DOMQuery $el)
    {
        $href = $el->attr('src');
        if (empty($href)) {
            return false;
        }

        if (preg_match('&(*UTF8)(youtube\.com|youtu\.be)&i', $href)) {
            return true;
        }
    }

    protected function getYouTubeAttributes(DOMQuery $el)
    {
        $youtube_attributes = '';

        // Preserve the data-*, width and height attributes only
        foreach ($el->attr() as $attr_name => $attr_value) {
            if (mb_strpos($attr_name, 'data-', 0, 'UTF-8') !== 0 && !in_array($attr_name, ['width', 'height'])) {
                continue;
            }

            if ($attr_name == 'height') {
                $height = (int)$attr_value;
                continue;
            }

            if ($attr_name == 'width') {
                $width = (int)$attr_value;
                continue;
            }

            $youtube_attributes .= " $attr_name = \"$attr_value\"";
        }

        if (empty($height) && !empty($width)) {
            $height = (int)($width / self::DEFAULT_ASPECT_RATIO);
        }

        if (!empty($height) && empty($width)) {
            $width = (int)($height * self::DEFAULT_ASPECT_RATIO);
        }

        if (empty($height) && empty($width)) {
            $width = self::DEFAULT_VIDEO_WIDTH;
            $height = self::DEFAULT_VIDEO_HEIGHT;
        }

        $youtube_attributes .= " height=\"$height\" width=\"$width\" ";
        return $youtube_attributes;
    }

    /**
     *
     * Get the youtube videoid
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getYouTubeCode(DOMQuery $el)
    {
        $matches = [];
        $youtube_code = '';
        $href = $el->attr('src');

        // @todo there seem to be a lot of ways to embed a youtube video. We probably need to capture all patterns here
        // The next one is the embed code that youtube gives you
        if (preg_match('&(*UTF8)/embed/([^/?]+)&i', $href, $matches)) {
            if (!empty($matches[1])) {
                $youtube_code = $matches[1];
                return $youtube_code;
            }
        }

        if (preg_match('&(*UTF8)youtu\.be/([^/?]+)&i', $href, $matches)) {
            if (!empty($matches[1])) {
                $youtube_code = $matches[1];
                return $youtube_code;
            }
        }

        if (preg_match('!(*UTF8)watch\?v=([^&]+)!i', $href, $matches)) {
            if (!empty($matches[1])) {
                $youtube_code = $matches[1];
                return $youtube_code;
            }
        }

        return $youtube_code;
    }
}
