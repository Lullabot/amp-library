<?php
namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class InfogramTransformPass
 * @package Lullabot\AMP\Pass
 */
class InfogramTransformPass extends BasePass
{
    function pass()
    {
        $all_infogram = $this->q->top()->find('div.infogram-embed');
        /** @var DOMQuery $el */
        foreach ($all_infogram as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);
            $script_tag = $this->getScriptTag($el, 'e\.infogram\.com/js\.js&i');
            
            $height = isset($this->options['infogram_height'])
                ? $this->options['infogram_height'] : 937;
            
            $width = isset($this->options['infogram_width'])
                ? $this->options['infogram_width'] : 1161;
            
            $src = $el->attr('data-id');
            
            if ($src) {
                $amp_string =<<<"HTML"
<amp-iframe 
    height="$height"
    width="$width"
    layout="responsive"
    frameborder="0"
    sandbox="allow-scripts allow-same-origin allow-popups"
    resizable="resizable"
    allowfullscreen="allowfullscreen"
    src="https://e.infogram.com/$src">
        <div style="visibility: hidden" overflow="overflow" tabindex="0" role="button" aria-label="Loading..." placeholder="placeholder">Loading...</div>
    </amp-iframe>
HTML;
                
                $el->after($amp_string);
                $new_dom_el = $el->get(0);
                
                if (!empty($script_tag)) {
                    $script_tag->remove();
                    $this->addActionTaken(new ActionTakenLine('a (with associated script tag)', ActionTakenType::INFOGRAM_CONVERTED, $lineno, $context_string));
                }
                else {
                    $this->addActionTaken(new ActionTakenLine('a', ActionTakenType::INFOGRAM_CONVERTED, $lineno, $context_string));
                }
                    $this->context->addLineAssociation($new_dom_el, $lineno);
            }
            else {
                $this->addActionTaken(new ActionTakenLine('div.infogram-embed', ActionTakenType::INFOGRAM_COULD_NOT_BE_CONVERTED, $lineno, $context_string));
            }
            
            // Remove the div, its children
            $el->removeChildren()->remove();
            
        }
        
        return $this->transformations;
    }
}