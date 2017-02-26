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
 * Class ObjectVideoTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all Video embed code <object> tags
 *
 * This is what a video embed looks like:
 *   <object width="480" height="270">
 *     <param name="movie" value="http://video.golem.de/player/videoplayer.swf?id=2883&autoPl=false">
 *     <param name="allowFullScreen" value="true">
 *     <param name="AllowScriptAccess" value="always">
 *     <embed src="http://video.golem.de/player/videoplayer.swf?id=2883&autoPl=false" type="application/x-shockwave-flash" allowfullscreen="true" AllowScriptAccess="always" width="480" height="270"></embed>
 *   </object>
 */
class ObjectVideoTagTransformPass extends BasePass
{
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
            
            if ($this->isVideoObject($el)) {
                $video_url = $this->getVideoUrl($el);

                if (empty($video_url)) {
                    continue;
                }

                $video_url = htmlspecialchars($video_url, ENT_QUOTES);
                $el->after("<a target=\"_blank\" href=\"{$video_url}\">{$video_url}</a>");
                $new_dom_el = $el->next()->get(0);

                $actionTakenType = ActionTakenType::OBJECT_CONVERTED_TO_A;
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

    protected function isVideoObject(DOMQuery $el)
    {
        $params = $el->find('param');
        foreach ($params as $param) {
            if ($param->attr('name') == 'movie') {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * Get the Video Url
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getVideoUrl(DOMQuery $el)
    {
        $matches = [];
        $video_url = '';
        $params = $el->find('param');

        foreach ($params as $param) {
            if ($param->attr('name') == 'movie') {
                return $param->attr('value');
            }
        }

        return $video_url;
    }
}
