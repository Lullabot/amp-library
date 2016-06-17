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
 * Class IframeFacebookTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform Facebook iframe embed code to the <amp-facebook> tag
 *
 * Sample embedded facebook post (iframe) for https://www.facebook.com/20531316728/posts/10154009990506729/
 * <iframe src="https://www.facebook.com/plugins/post.php?
 * href=https%3A%2F%2Fwww.facebook.com%2F20531316728%2Fposts%2F10154009990506729%2F&width=500&show_text=true&height=290&appId"
 * width="500" height="290" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true"></iframe>
 *
 * Sample embedded facebook video (iframe) for https://www.facebook.com/facebook/videos/10153231379946729/
 * <iframe src="https://www.facebook.com/plugins/video.php?
 * href=https%3A%2F%2Fwww.facebook.com%2Ffacebook%2Fvideos%2F10153231379946729%2F&width=500&show_text=false&height=281&appId"
 * width="500" height="281" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true"></iframe>
 *
 * Sample <amp-facebook> tag for a post:
 * <amp-facebook width="552" height="303" layout="responsive" data-href="https://www.facebook.com/zuck/posts/10102593740125791"></amp-facebook>
 *
 * Sample <amp-facebook> tag for a video
 * <amp-facebook width="552" height="310" layout="responsive" data-href="https://www.facebook.com/zuck/videos/10102509264909801/"></amp-facebook>
 *
 * Facebook Developer Documentation
 * @see https://developers.facebook.com/docs/plugins/
 *
 * AMP Project related Developer Documentation
 * @see https://www.ampproject.org/docs/reference/extended/amp-facebook.html
 * @see https://ampbyexample.com/components/amp-facebook/
 * @see https://github.com/ampproject/amphtml/blob/master/extensions/amp-facebook/amp-facebook.md
 * @see https://github.com/ampproject/amphtml/blob/master/extensions/amp-facebook/0.1/validator-amp-facebook.protoascii
 */
class IframeFacebookTagTransformPass extends BasePass
{
    const DEFAULT_ASPECT_RATIO = 1.7778;
    const DEFAULT_WIDTH = 500;
    const DEFAULT_HEIGHT = 281;

    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);

            // This should run before setStandardAttributes because of the side effect of that method. See comment below.
            $context_string = $this->getContextString($dom_el);

            // Function results in some side effects that are used later on. The side effect is the addition
            // of some attributes (see method) that are copied over by the setStandardAttributes method below.
            if (!$this->setStandardFacebookParameters($el)) {
                continue;
            }

            $el->after('<amp-facebook layout="responsive"></amp-facebook>');
            $new_el = $el->next();
            $new_dom_el = $new_el->get(0);
            $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_WIDTH, self::DEFAULT_HEIGHT, self::DEFAULT_ASPECT_RATIO);

            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::FACEBOOK_IFRAME_CONVERTED, $lineno, $context_string));

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     * @return bool
     */
    protected function setStandardFacebookParameters(DOMQuery $el)
    {
        $src = $el->attr('src');
        if (empty($src)) {
            return false;
        }

        $query_arr = $this->getQueryArray($el);
        if (!isset($query_arr['href'])) {
            return false;
        }

        // e.g https://www.facebook.com/facebook/videos/10153231379946729/
        if (preg_match('&(*UTF8)facebook\.com/facebook/videos/\d+/?&i', $query_arr['href'])) {
            // A facebook video can be embedded as a post. Doing that enables the video "card" to display
            if (isset($query_arr['show_text']) && $query_arr['show_text'] !== "false") {
                $embed_as = 'post';
            } else {
                $embed_as = 'video';
            }
        } // e.g. https://www.facebook.com/20531316728/posts/10154009990506729/
        else if (preg_match('&(*UTF8)facebook\.com/.*/posts/\d+/?&i', $query_arr['href'])) {
            $embed_as = 'post';
        } else {
            return false;
        }

        if (isset($query_arr['width']) && empty($el->attr('width'))) {
            // will automatically get copied over the the amp-facebook tag
            $el->attr('width', $query_arr['width']);
        }

        if (isset($query_arr['height']) && empty($el->attr('height'))) {
            // will automatically get copied over the the amp-facebook tag
            $el->attr('height', $query_arr['height']);
        }

        // will automatically get copied over the the amp-facebook tag
        $el->attr('data-href', $query_arr['href']);
        $el->attr('data-embed-as', $embed_as);
        return true;
    }
}
