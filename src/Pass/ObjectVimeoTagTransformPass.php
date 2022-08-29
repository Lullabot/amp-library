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
 * Class ObjectVimeoTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all Vimeo embed code <object> tags which don't have noscript as an ancestor to <amp-vimeo> tags
 *
 * This is what a vimeo embed looks like:
 *   <object width="550" height="309">
 *     <param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=12223465&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=00ADEF&amp;fullscreen=1" />
 *     <param name="allowfullscreen" value="true" />
 *     <param name="allowscriptaccess" value="always" />
 *     <embed src="http://vimeo.com/moogaloop.swf?clip_id=12223465&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=00ADEF&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="550" height="309"></embed>
 *   </object>
 *
 * @see https://github.com/ampproject/amphtml/blob/main/extensions/amp-vimeo/amp-vimeo.md
 *
 */
class ObjectVimeoTagTransformPass extends BasePass
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

            if ($this->isVimeoObject($el)) {
                $vimeo_code = $this->getVimeoCode($el);

                // If we couldnt find a vimeo videoid then we abort
                if (empty($vimeo_code)) {
                    continue;
                }

                $el->after('<amp-vimeo width="16" height="9" layout="responsive" data-videoid="' . $vimeo_code . '"></amp-vimeo>');
                $new_dom_el = $el->next()->get(0);

                $actionTakenType = ActionTakenType::VIMEO_OBJECT_CONVERTED;

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

    protected function isVimeoObject(DOMQuery $el)
    {
        $params = $el->find('param');
        foreach ($params as $param) {
            if ($param->attr('name') == 'movie') {
                $param_value = $param->attr('value');
                if (preg_match('&(*UTF8)(vimeo\.com)&i', $param_value)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     *
     * Get the Vimeo Code
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getVimeoCode(DOMQuery $el)
    {
        $matches = [];
        $vimeo_code = '';
        $params = $el->find('param');

        foreach ($params as $param) {
            if ($param->attr('name') == 'movie') {
                $param_value = $param->attr('value');

                $pattern = '#http://(?:\w+.)?vimeo.com/(?:video/|moogaloop\.swf\?clip_id=)(\w+)#i';
                if (preg_match($pattern, $param_value, $matches)) {
                    if (!empty($matches[1])) {
                        $vimeo_code = $matches[1];
                        return $vimeo_code;
                    }
                }
            }
        }

        return $vimeo_code;
    }
}
