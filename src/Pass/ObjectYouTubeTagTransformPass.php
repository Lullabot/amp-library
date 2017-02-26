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
 * Class ObjectYouTubeTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all Youtube embed code <object> tags which don't have noscript as an ancestor to <amp-youtube> tags
 *
 * This is what a youtube embed looks like:
 *   <object width="512" height="308">
 *     <param name="movie" value="https://www.youtube.com/embed/MnR9AVs6Q_c">
 *     <param name="allowFullScreen" value="true">
 *     <param name="allowscriptaccess" value="always">
 *     <embed src="https://www.youtube.com/embed/MnR9AVs6Q_c" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="512" height="308">
 *   </object>
 *
 * @see https://github.com/ampproject/amphtml/blob/master/extensions/amp-youtube/amp-youtube.md
 *
 */
class ObjectYouTubeTagTransformPass extends BasePass
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
        $all_objects = $this->q->find('object:not(noscript object)');
        /** @var DOMQuery $el */
        foreach ($all_objects as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);

            $actionTakenType = '';
            
            if ($this->isYouTubeObject($el)) {
                $youtube_code = $this->getYouTubeCode($el);

                // If we couldnt find a youtube videoid then we abort
                if (empty($youtube_code)) {
                    continue;
                }

                $el->after("<amp-youtube data-videoid=\"$youtube_code\" layout=\"responsive\"></amp-youtube>");

                $new_el = $el->next();
                $new_dom_el = $new_el->get(0);

                $actionTakenType = ActionTakenType::YOUTUBE_OBJECT_CONVERTED;

                $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_VIDEO_WIDTH, self::DEFAULT_VIDEO_HEIGHT, self::DEFAULT_ASPECT_RATIO);
            
            } else {
                continue;
            }

            // Remove the object and its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('object', $actionTakenType, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
            
        }

        return $this->transformations;
    }

    protected function isYouTubeObject(DOMQuery $el)
    {
        $params = $el->find('param');
        foreach ($params as $param) {
            if ($param->attr('name') == 'movie') {
                $param_value = $param->attr('value');
                if (preg_match('&(*UTF8)(youtube\.com|youtu\.be)&i', $param_value)) {
                    return true;
                }
            }
        }

        return false;
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
        $params = $el->find('param');

        foreach ($params as $param) {
            if ($param->attr('name') == 'movie') {
                $param_value = $param->attr('value');

                $pattern = 
                    '~(?#!js YouTubeId Rev:20160125_1800)
                    # Match non-linked youtube URL in the wild. (Rev:20130823)
                    https?://          # Required scheme. Either http or https.
                    (?:[0-9A-Z-]+\.)?  # Optional subdomain.
                    (?:                # Group host alternatives.
                      youtu\.be/       # Either youtu.be,
                    | youtube          # or youtube.com or
                      (?:-nocookie)?   # youtube-nocookie.com
                      \.com            # followed by
                      \S*?             # Allow anything up to VIDEO_ID,
                      [^\w\s-]         # but char before ID is non-ID char.
                    )                  # End host alternatives.
                    ([\w-]{11})        # $1: VIDEO_ID is exactly 11 chars.
                    (?=[^\w-]|$)       # Assert next char is non-ID or EOS.
                    (?!                # Assert URL is not pre-linked.
                      [?=&+%\w.-]*     # Allow URL (query) remainder.
                      (?:              # Group pre-linked alternatives.
                        [\'"][^<>]*>   # Either inside a start tag,
                      | </a>           # or inside <a> element text contents.
                      )                # End recognized pre-linked alts.
                    )                  # End negative lookahead assertion.
                    [?=&+%\w.-]*       # Consume any URL (query) remainder.
                    ~ix';
                if (preg_match($pattern, $param_value, $matches)) {
                    if (!empty($matches[1])) {
                        $youtube_code = $matches[1];
                        return $youtube_code;
                    }
                }
            }
        }

        return $youtube_code;
    }
}
