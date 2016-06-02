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
 * Class InstagramTransformPass
 * @package Lullabot\AMP\Pass
 */
class InstagramTransformPass extends BasePass
{
    const DEFAULT_HEIGHT = 400;
    const DEFAULT_WIDTH = 400;

    function pass()
    {
        $all_instagram = $this->q->find('blockquote[class="instagram-media"]');
        /** @var DOMQuery $el */
        foreach ($all_instagram as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $shortcode = $this->getShortcode($el);
            // If we can't get the instagram shortcode, abort
            if (empty($shortcode)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);
            $instagram_script_tag = $this->getScriptTag($el, '&(*UTF8)instagram\.com/.*/embeds.js&i');

            // Dealing with height and width is going to be tricky
            // https://github.com/ampproject/amphtml/blob/master/extensions/amp-instagram/amp-instagram.md
            // @todo make this smarter
            /** @var \DOMElement $new_dom_el */
            $el->after('<amp-instagram layout="responsive" width="' . self::DEFAULT_WIDTH . '" height="' . self::DEFAULT_HEIGHT . '" data-shortcode="' . $shortcode . '"></amp-instagram>');
            $new_dom_el = $el->next()->get(0);

            // Remove the blockquote, its children and the instagram script tag that follows after the blockquote
            $el->removeChildren()->remove();
            if (!empty($instagram_script_tag)) {
                $instagram_script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('blockquote.instagram-media (with associated script tag)', ActionTakenType::INSTAGRAM_CONVERTED, $lineno, $context_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('blockquote.instagram-media', ActionTakenType::INSTAGRAM_CONVERTED, $lineno, $context_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * Get instagram shortcode from the instagram embed code
     */
    protected function getShortcode(DOMQuery $el)
    {
        $links = $el->find('a');
        /** @var DOMQuery $link */
        $shortcode = '';
        // Get the shortcode from the first <a> tag that matches regex and exit
        foreach ($links as $link) {
            $href = $link->attr('href');
            $matches = [];
            if (preg_match('&(*UTF8)instagram.com/p/([^/]+)/?&i', $href, $matches)) {
                if (!empty($matches[1])) {
                    $shortcode = $matches[1];
                    break;
                }
            }
        }

        return $shortcode;
    }
}
