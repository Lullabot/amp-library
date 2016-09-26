<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class SugarImgurPass
 * @package Lullabot\AMP\Pass
 */
class SugarImgurTransformPass extends ImgTagTransformPass
{
    function pass()
    {
        $all_imgur = $this->q->top()->find('blockquote.imgur-embed-pub');
        /** @var DOMQuery $el */
        foreach ($all_imgur as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);

            /** @var \DOMElement $new_dom_el */
            $imgur_id = $el->attr('data-id');
            $img_src = 'https://i.imgur.com/' . $imgur_id . '.png';
            $size = $this->getImageWidthHeight($img_src);

            if (!$size) {
                $size['height'] = 400;
                $size['width'] = 400;
            }

            $amp_string =<<<"HTML"
<amp-iframe 
    height="{$size['height']}"
    width="{$size['width']}"
    layout="responsive"
    frameborder="0"
    sandbox="allow-scripts allow-same-origin"
    src="https://imgur.com/$imgur_id/embed">
    <amp-img layout="fill" src="$img_src" placeholder=''></amp-img>
</amp-iframe>
HTML;

            $el->after($amp_string);
            $new_dom_el = $el->get(0);

            // Remove the blockquote, its children
            $el->removeChildren()->remove();
            $this->addActionTaken(new ActionTakenLine('blockquote.imgur', ActionTakenType::IMGUR_CONVERTED, $lineno, $context_string));
            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }
}
