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
 * Class PinterestTag
 * @package Lullabot\AMP\Pass
 *
 * Sample pinterest embed code:
 * <a data-pin-do="embedPin" data-pin-width="medium" href="https://www.pinterest.com/pin/99360735500167749/"></a>
 * <script async defer src="//assets.pinterest.com/js/pinit.js"></script>
 *
 * @see https://www.ampproject.org/docs/reference/extended/amp-pinterest.html
 * @see https://developers.pinterest.com/tools/widget-builder/
 *
 */
class PinterestTagTransformPass extends BasePass
{
    function pass()
    {
        $all_pinterest = $this->q->find('a[data-pin-do="embedPin"]');
        /** @var DOMQuery $el */
        foreach ($all_pinterest as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $data_url = $el->attr('href');
            if (empty($data_url)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);
            $script_tag = $this->getScriptTag($el, '&(*UTF8)pinterest\.com/js/pinit\.js&i');
            $pinterest_dimensions = $this->getPinterestDimensions($el);

            // hard code width and height for now (medium size pin)
            // layout="responsive" is not the way to go. Omit that.
            $el->after('<amp-pinterest ' . $pinterest_dimensions . ' data-url="' . $data_url . '" data-do="embedPin"></amp-pinterest>');
            $new_dom_el = $el->next()->get(0);

            // Remove the a, its children and the script tag that may follow after the a tag
            $el->removeChildren()->remove();
            if (!empty($script_tag)) {
                $script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('a (with associated script tag)', ActionTakenType::PINTEREST_CONVERTED, $lineno, $context_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('a', ActionTakenType::PINTEREST_CONVERTED, $lineno, $context_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * @param DOMQuery $el
     * @return string
     */
    protected function getPinterestDimensions($el)
    {
        $pin_width = trim($el->attr('data-pin-width'));
        if ($pin_width == 'medium') {
            return ' width="345" height="426" data-width="medium" ';
        } else if ($pin_width == 'large') {
            return ' width="562" height="627" data-width="large" ';
        } else {
            return ' width="236" height="345" data-width="small" ';
        }
    }
}
