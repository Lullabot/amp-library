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

use Lullabot\AMP\Validate\Scope;
use QueryPath\DOMQuery;

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;
use FastImageSize\FastImageSize;

/**
 * Class NoscriptTagWorkaroundPass
 * @package Lullabot\AMP\Pass
 *
 * This pass is a workaround for an issue in Mastermind HTML5 output.
 *
 * The <noscript> tag in <head> allows <meta>, <link> and <style> tags and when in <body> many more tags.
 * (See https://html.spec.whatwg.org/multipage/scripting.html#the-noscript-element)
 *
 * However, the mastermind HTML5 parser treats everything in <noscript> as raw text. This causes an ugly PHP notice and
 * an empty <noscript> tag when doing $qp->top()->html5() in the AMP::convertToAmpHtml() method.
 *
 * Here we simply add the innerHTML() contents back as text so the Mastermind HTML5 output works without any problems.
 *
 * @see https://github.com/Lullabot/amp-library/issues/22
 * @see https://github.com/Masterminds/html5-php/issues/98
 */
class NoscriptTagWorkaroundPass extends BasePass
{
    function pass()
    {
        $noscripts = $this->q->top()->find('noscript');
        /** @var DOMQuery $noscript */
        foreach ($noscripts as $noscript) {
            /** @var \DOMElement $noscript_dom_el */
            $noscript_dom_el = $noscript->get(0);
            $inner_html = $this->noscriptInnerHtml($noscript_dom_el);
            $noscript->removeChildren();
            $noscript->text($inner_html);
        }

        return [];
    }

    /**
     * Substitute for \QueryPath\DOMQuery::innerHTML() (which is basically innerXML())
     *
     * @param \DOMElement $el
     * @return string
     */
    function noscriptInnerHtml(\DOMElement $el)
    {
        $inner_html = '';
        // Similar to loop in \QueryPath\DOMQuery::innerXML() except we don't use saveXML as it generates CDATA stuff
        // (which we don't want) and use saveHTML instead
        /** @var \DOMElement $child_node */
        foreach ($el->childNodes as $child_node) {
            $inner_html .= $child_node->ownerDocument->saveHTML($child_node);
        }
        return $inner_html;
    }
}
