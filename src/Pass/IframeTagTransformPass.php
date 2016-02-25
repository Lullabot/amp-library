<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class IframeTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all <iframe> tags which don't have noscript as an ancestor to <amp-iframe> tags
 */
class IframeTagTransformPass extends BasePass
{
    function pass()
    {
        $all_a = $this->q->find('iframe:not(noscript iframe)');
        /** @var \DOMElement $dom_el */
        foreach ($all_a->get() as $dom_el) {
            $lineno = $dom_el->getLineNo();
            $context_string = $this->getContextString($dom_el);

            $new_el = $this->renameDomElement($dom_el, 'amp-iframe');
            $this->setAmpIframeAttributes($new_el);
            $this->context->addLineAssociation($new_el, $lineno);
            $this->addActionTaken(new ActionTakenLine('iframe', ActionTakenType::IFRAME_CONVERTED, $lineno, $context_string));
        }

        return $this->warnings;
    }

    protected function setAmpIframeAttributes(\DOMElement $el)
    {
        // Sane default for now
        if (!$el->hasAttribute('layout')) {
            $el->setAttribute('layout', 'responsive');
        }

        if (!$el->hasAttribute('sandbox')) {
            $el->setAttribute('sandbox', 'allow-scripts allow-same-origin');
        }
    }
}