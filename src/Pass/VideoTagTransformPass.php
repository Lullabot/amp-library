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
 * Class VideoTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Support for <video> to <amp-video> tag conversion. Similar to AudioTagTransformPass in many ways.
 *
 * @see https://github.com/ampproject/amphtml/blob/main/builtins/amp-video.md
 * @see https://ampbyexample.com/components/amp-video/
 * @see https://developer.mozilla.org/en/docs/Web/HTML/Element/video
 */
class VideoTagTransformPass extends BasePass
{
    const DEFAULT_ASPECT_RATIO = 1.7778;
    const DEFAULT_VIDEO_WIDTH = 560;
    const DEFAULT_VIDEO_HEIGHT = 315;

    function pass()
    {
        $audio_tags = $this->q->find('video:not(noscript video)');
        /** @var DOMQuery $el */
        foreach ($audio_tags as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);

            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);

            $new_dom_el = $this->cloneAndRenameDomElement($dom_el, 'amp-video');
            $new_el = $el->prev();

            $this->addFallbackAndPlaceholder($new_el);
            $this->setLayoutIfNoLayout($new_el, 'responsive');
            $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_VIDEO_WIDTH, self::DEFAULT_VIDEO_HEIGHT, self::DEFAULT_ASPECT_RATIO);

            // Remove old video tag
            $el->remove();

            $this->addActionTaken(new ActionTakenLine('video', ActionTakenType::VIDEO_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     */
    function addFallbackAndPlaceholder(DOMQuery $el)
    {
        /** @var DOMQuery $wrap_this */
        $wrap_this = $el->children()->not('source')->not('track');
        if ($wrap_this->count()) {
            $wrapped = $wrap_this->wrapAll('<div fallback=""></div>')->parent();
            $wrapped->remove()->prependTo($el);
        }

        if (isset($this->options['video_placeholder_html'])) {
            $el->prepend('<div placeholder="">' . $this->options['video_placeholder_html'] . '</div>');
        }
    }
}
