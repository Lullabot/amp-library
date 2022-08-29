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
 * Class FacebookNonIframeTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform Facebook non-iframe embed code to the <amp-facebook> tag
 *
 * Facebook Developer Documentation
 * @see https://developers.facebook.com/docs/plugins/
 *
 * AMP Project related Developer Documentation
 * @see https://www.ampproject.org/docs/reference/extended/amp-facebook.html
 * @see https://ampbyexample.com/components/amp-facebook/
 * @see https://github.com/ampproject/amphtml/blob/main/extensions/amp-facebook/amp-facebook.md
 * @see https://github.com/ampproject/amphtml/blob/main/extensions/amp-facebook/0.1/validator-amp-facebook.protoascii
 */
class FacebookNonIframeTransformPass extends BaseFacebookPass
{
    const DEFAULT_WIDTH = 500;
    const DEFAULT_HEIGHT = 281;
    const DEFAULT_HEIGHT_WITH_CARD = 366;

    function pass()
    {
        $all_fb = $this->q->find('div.fb-post,div.fb-video,script');
        /** @var DOMQuery $el */
        foreach ($all_fb as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $tagname = 'div.fb-video';
            $context_string = $this->getContextString($dom_el);

            if ($el->is('div.fb-post')) {
                $tagname = 'div.fb-post';
            } else if ($el->is('script')) {
                $this->processIfFacebookScriptTag($el, $lineno, $context_string);
                continue;
            }

            $attrs = $this->getFacebookEmbedAttrs($el);
            if (empty($attrs)) {
                continue;
            }

            $el->after('<amp-facebook layout="responsive"></amp-facebook>');
            $new_el = $el->next();
            $new_el->attr($attrs);
            $new_dom_el = $new_el->get(0);

            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine($tagname, ActionTakenType::FACEBOOK_JSDK_CONVERTED, $lineno, $context_string));

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     * @param string $lineno
     * @param string $context_string
     */
    protected function processIfFacebookScriptTag(DOMQuery $el, $lineno, $context_string)
    {
        $script_contents = $el->text();
        if (strpos($script_contents, "'facebook-jssdk'") === false) {
            return;
        }

        $el->remove();
        $this->addActionTaken(new ActionTakenLine('script', ActionTakenType::FACEBOOK_SCRIPT_REMOVED, $lineno, $context_string));
    }

    /**
     * @param DOMQuery $el
     * @return string[]|bool
     */
    protected function getFacebookEmbedAttrs(DOMQuery $el)
    {
        $src = $el->attr('data-href');
        if (empty($src)) {
            return false;
        }

        $card = true;
        if ($this->isValidVideoUrl($src)) {
            // A facebook video can be embedded as a post. Doing that enables the video "card" to display
            if ($el->attr('data-show-text') !== "false") {
                $embed_as = 'post';
                $card = true;
            } else {
                $embed_as = 'video';
            }
        }
        elseif ($this->isValidPostUrl($src)) {
            $embed_as = 'post';
        } else {
            return false;
        }

        // This is going to be responsive. We worry about aspect ratio and not specific numbers
        if (!empty($el->attr('width')) && !empty($el->attr('height'))) {
            // We're very rarely going to be in this if branch as only width is provided and
            // height does not seem to be in non-iframe embeds
            $width = $el->attr('width');
            $height = $el->attr('height');
        } else {
            // This is going to be the most common case
            $width = self::DEFAULT_WIDTH;
            $height = $card ? self::DEFAULT_HEIGHT_WITH_CARD : self::DEFAULT_HEIGHT;
        }

        $attrs = [
            'data-href' => $src,
            'data-embed-as' => $embed_as,
            'height' => $height,
            'width' => $width
        ];

        // Set locale if exists.
        if (!empty($el->attr('data-locale'))) {
            $attrs['data-locale'] = $el->attr('data-locale');
        }

        return $attrs;
    }
}
