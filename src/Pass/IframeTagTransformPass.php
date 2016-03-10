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

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class IframeTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all <iframe> tags which don't have noscript as an ancestor to <amp-iframe> tags
 * This class is very similar to the IframeYouTubeTagTransformationPass class.
 *
 */
class IframeTagTransformPass extends BasePass
{
    /**
     * A standard iframe aspect ratio; used when we don't have enough height/width information
     * @var float
     */
    const DEFAULT_ASPECT_RATIO = 1.7778;
    const DEFAULT_WIDTH = 560;
    const DEFAULT_HEIGHT = 315;

    function pass()
    {
        $all_iframes = $this->q->find('iframe:not(noscript iframe)');
        /** @var DOMQuery $el */
        foreach ($all_iframes as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);

            $lineno = $dom_el->getLineNo();
            $context_string = $this->getContextString($dom_el);

            $iframe_attributes = $this->getIframeAttributes($el);
            // We need to do this separately. If the src has a character like '&' then $el->after has problems
            // and we get a "Entity: line 1: parser error : EntityRef: expecting ';'" error
            $src = $this->getIframeSrc($el);

            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-iframe $iframe_attributes sandbox=\"allow-scripts allow-same-origin\" layout=\"responsive\"></amp-iframe>");
            $new_dom_el = $el->next()->get(0);
            $new_dom_el->setAttribute('src', $src);

            // Remove the iframe and its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::IFRAME_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    protected function getIframeSrc(DOMQuery $el) {
        return $el->attr('src');
    }

    protected function getIframeAttributes(DOMQuery $el)
    {
        $iframe_attributes = '';

        // Preserve the data-*, width, height and class attributes only
        foreach ($el->attr() as $attr_name => $attr_value) {
            if (mb_strpos($attr_name, 'data-', 0, 'UTF-8') !== 0 && !in_array($attr_name, ['width', 'height', 'class'])) {
                continue;
            }

            if ($attr_name == 'height') {
                $height = (int)$attr_value;
                continue;
            }

            if ($attr_name == 'width') {
                $width = (int)$attr_value;
                continue;
            }

            $iframe_attributes .= " $attr_name = \"$attr_value\"";
        }

        if (empty($height) && !empty($width)) {
            $height = (int)($width / self::DEFAULT_ASPECT_RATIO);
        }

        if (!empty($height) && empty($width)) {
            $width = (int)($height * self::DEFAULT_ASPECT_RATIO);
        }

        if (empty($height) && empty($width)) {
            $width = self::DEFAULT_WIDTH;
            $height = self::DEFAULT_HEIGHT;
        }

        $iframe_attributes .= " height=\"$height\" width=\"$width\" ";
        return $iframe_attributes;
    }
}
