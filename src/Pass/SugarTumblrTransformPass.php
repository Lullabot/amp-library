<?php
namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class SugarTumblrPass
 * @package Lullabot\AMP\Pass
 */
class SugarTumblrTransformPass extends BasePass
{
    function pass()
    {
        $all_tumblr = $this->q->top()->find('div.tumblr-post');
        /** @var DOMQuery $el */
        foreach ($all_tumblr as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);

            $height = isset($this->options['tumblr_height'])
                ? $this->options['tumblr_height'] : 360;

            $width = isset($this->options['tumblr_width'])
                ? $this->options['tumblr_width'] : 414;

            $src = $el->attr('data-href');

            if ($src) {
                $amp_string =<<<"HTML"
<amp-iframe 
    height="$height"
    width="$width"
    layout="responsive"
    frameborder="0"
    sandbox="allow-scripts allow-same-origin"
    src="$src"></amp-iframe>
HTML;

                $el->after($amp_string);
                $new_dom_el = $el->get(0);
                $this->addActionTaken(new ActionTakenLine('div.tumblr-post', ActionTakenType::TUMBLR_CONVERTED, $lineno, $context_string));
                $this->context->addLineAssociation($new_dom_el, $lineno);
            }
            else {
                $this->addActionTaken(new ActionTakenLine('div.tumblr-post', ActionTakenType::TUMBLR_COULD_NOT_BE_CONVERTED, $lineno, $context_string));
            }

            // Remove the div, its children
            $el->removeChildren()->remove();

        }

        return $this->transformations;
    }
}
