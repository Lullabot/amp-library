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
 * Class IframeVineTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * "Simple" mode Vine embed code:
 * <iframe src="https://vine.co/v/ixtpg1zrLQt/embed/simple" width="600" height="600" frameborder="0"></iframe>
 * <script src="https://platform.vine.co/static/scripts/embed.js"></script>
 *
 * "Postcard" mode Vine embed code:
 * <iframe src="https://vine.co/v/ixtpg1zrLQt/embed/postcard" width="600" height="600" frameborder="0"></iframe>
 * <script src="https://platform.vine.co/static/scripts/embed.js"></script>
 *
 * Audio enabled:
 * <iframe src="https://vine.co/v/ixtpg1zrLQt/embed/postcard?audio=1" width="600" height="600" frameborder="0">
 * </iframe><script src="https://platform.vine.co/static/scripts/embed.js"></script>
 *
 * Height/Width changed:
 * <iframe src="https://vine.co/v/ixtpg1zrLQt/embed/postcard?audio=1" width="480" height="480" frameborder="0"></iframe>
 * <script src="https://platform.vine.co/static/scripts/embed.js"></script>
 *
 * @see https://www.ampproject.org/docs/reference/extended/amp-vine.html
 */
class IframeVineTagTransformPass extends BasePass
{
    const DEFAULT_HEIGHT = 480;
    const DEFAULT_WIDTH = 480;
    const DEFAULT_ASPECT_RATIO = 1.0;

    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $vineid = $this->getArtifactId($el, '&(*UTF8)vine\.co/v/([^/]+)/?&i');
            // If we can't get the vineid, abort
            if (empty($vineid)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);
            $script_tag = $this->getScriptTag($el, '&(*UTF8)vine\.co/static/scripts/embed\.js&i');

            $el->after('<amp-vine layout="responsive" data-vineid="' . $vineid . '"></amp-vine>');
            $new_el = $el->next();
            $new_dom_el = $new_el->get(0);
            $this->setStandardAttributesFrom($el, $new_el, self::DEFAULT_WIDTH, self::DEFAULT_HEIGHT, self::DEFAULT_ASPECT_RATIO);

            $el->removeChildren()->remove();
            if (!empty($script_tag)) {
                $script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('iframe (with associated script tag)', ActionTakenType::VINE_CONVERTED, $lineno, $context_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::VINE_CONVERTED, $lineno, $context_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }
}
