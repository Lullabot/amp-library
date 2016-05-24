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
 * Class IframeSoundCloudTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Sample SoundCloud embed
 * <iframe width="100%" height="450" scrolling="no" frameborder="no"
 * src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/238184768&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true">
 * </iframe>
 *
 * Sample <amp-soundcloud> tags:
 * Visual Mode:
 * <amp-soundcloud height=657 layout="fixed-height" data-trackid="243169232" data-visual="true"></amp-soundcloud>
 *
 * Classic Mode:
 * <amp-soundcloud height=657 layout="fixed-height" data-trackid="243169232" data-color="ff5500"></amp-soundcloud>
 *
 * @see https://www.ampproject.org/docs/reference/extended/amp-soundcloud.html
 */
class IframeSoundCloudTagTransformPass extends BasePass
{
    const DEFAULT_HEIGHT = 450;

    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            if (!$this->isSoundCloudIframe($el)) {
                continue;
            }

            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);
            $track_id = $this->getTrackId($el);

            // If we couldnt find a soundcloud track id then we abort
            if (empty($track_id)) {
                continue;
            }

            $attributes = $this->getTrackAttributes($el);

            if ($el->hasAttr('class')) {
                $class_attr = $el->attr('class');
            }

            $tag_string = "<amp-soundcloud data-trackid=\"$track_id\" layout=\"fixed-height\" $attributes ></amp-soundcloud>";
            /** @var \DOMElement $new_dom_el */
            $el->after($tag_string);
            $new_dom_el = $el->next()->get(0);
            if (!empty($class_attr)) {
                $new_dom_el->setAttribute('class', $class_attr);
            }

            // Remove the iframe and its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::SOUNDCLOUD_IFRAME_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    protected function isSoundCloudIframe(DOMQuery $el)
    {
        $href = $el->attr('src');
        if (empty($href)) {
            return false;
        }

        if (preg_match('&(*UTF8)w\.soundcloud\.com/player/?\?&i', $href)) {
            return true;
        }

        return false;
    }

    protected function getTrackAttributes(DOMQuery $el)
    {
        $attributes = '';
        $query_arr = $this->getQueryArray($el);

        if (isset($query_arr['visual'])) {
            $attributes = " data-visual=\"{$query_arr['visual']}\" ";
        }

        // Preserve the data-*, height attributes only
        foreach ($el->attr() as $attr_name => $attr_value) {
            if (mb_strpos($attr_name, 'data-', 0, 'UTF-8') !== 0 && !in_array($attr_name, ['height'])) {
                continue;
            }

            if ($attr_name == 'height') {
                $height = (int)$attr_value;
                continue;
            }

            $attributes .= " $attr_name = \"$attr_value\"";
        }

        if (empty($height)) {
            $height = self::DEFAULT_HEIGHT;
        }

        $attributes .= " height=\"$height\" ";
        return $attributes;

    }

    protected function getTrackId(DOMQuery $el)
    {
        $arr = $this->getQueryArray($el);
        if (empty($arr['url'])) {
            return false;
        }

        $matches = [];
        if (!preg_match('&(*UTF8)api\.soundcloud\.com/tracks/(\d+)&i', $arr['url'], $matches)) {
            return true;
        }

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return false;
    }
}
