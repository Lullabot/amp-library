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
 * Class IframeDailymotionTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Sample Dailymotion embed code:
 * <iframe frameborder="0" width="480" height="270" src="//www.dailymotion.com/embed/video/x494jb9" allowfullscreen></iframe><br />
 * <a href="http://www.dailymotion.com/video/x494jb9_the-hulk-vs-iron-man-mark-46_videogames" target="_blank">
 * The Hulk vs Iron Man - Mark 46</a> <i>by <a href="http://www.dailymotion.com/KjraGaming" target="_blank">KjraGaming</a></i>
 *
 * @see https://www.ampproject.org/docs/reference/extended/amp-dailymotion.html
 * @see https://developer.dailymotion.com/player#player-parameters
 */
class IframeDailymotionTagTransformPass extends BasePass
{
    const DEFAULT_HEIGHT = 270;
    const DEFAULT_WIDTH = 480;
    const DEFAULT_ASPECT_RATIO = 1.77778;

    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $videoid = $this->getArtifactId($el, '&(*UTF8)dailymotion\.com/embed/video/([^/\?]+)&i');
            // If we can't get the videoid, abort
            if (empty($videoid)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);

            $el->after('<amp-dailymotion layout="responsive" data-videoid="' . $videoid . '"></amp-dailymotion>');
            $new_el = $el->next();
            $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_WIDTH, self::DEFAULT_HEIGHT, self::DEFAULT_ASPECT_RATIO);

            // Add some advanced attributes like start time etc. if they are available in the iframe src url
            $this->addDailyMotionAttributes($el, $new_el);
            $new_dom_el = $new_el->get(0);

            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::DAILYMOTION_CONVERTED, $lineno, $context_string));

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     * @param DOMQuery $new_dom_el
     */
    protected function addDailyMotionAttributes($el, $new_dom_el)
    {
        /*
         * mute -> data-mute
         * endscreen-enable -> data-endscreen-enable
         * sharing-enable -> data-sharing-enable
         * start -> data-start
         * ui-highlight -> data-ui-highlight
         * ui-logo -> data-ui-logo
         * ui-start_screen_info -> data-info (?)
         */
        $arr = $this->getQueryArray($el);
        if (isset($arr['ui-start_screen_info'])) {
            $arr['info'] = $arr['ui-start_screen_info'];
            unset($arr['ui-start_screen_info']);
        }

        $possible_query_names = ['mute', 'endscreen-enable', 'sharing-enable', 'start', 'ui-highlight', 'ui-logo', 'info'];
        foreach ($arr as $query_name => $query_value) {
            if (in_array($query_name, $possible_query_names)) {
                $new_dom_el->attr("data-$query_name", $query_value);
            }
        }
    }
}
