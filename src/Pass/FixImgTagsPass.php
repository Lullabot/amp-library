<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

/**
 * Class FixImgTagsPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all <img> tags to <amp-img> which don't have noscript as an ancestor
 * - height and width are obtained by trying to look at the image file itself via getimagesize()
 * - Currently the layout is set to responsive
 */
class FixImgTagsPass extends FixBasePass
{
    function pass()
    {
        // @todo deal with animated gifs
        $all_a = $this->q->find(':not(noscript) img');
        /** @var \DOMElement $dom_el */
        foreach ($all_a->get() as $dom_el) {
            $lineno = $dom_el->getLineNo();

            $new_el = $this->renameDomElement($dom_el, 'amp-img');
            $this->setAmpImgAttributes($new_el);

            $this->addWarning(new Warning('img', WarningType::IMG_CONVERTED_AMP_IMG, ActionTaken::TAG_RENAMED, $lineno));
        }

        return $this->warnings;
    }

    // @todo deal with failure
    // @todo should this call out to externally registered callbacks?
    protected function setAmpImgAttributes(\DOMElement $el)
    {

        $src = $el->getAttribute('src');

        // Try obtaining image size
        $size = getimagesize($src);

        if (empty($size)) {
            $src = $this->options['base_uri'] . $src;
        }

        $size = getimagesize($src);

        $width = $size[0];
        $height = $size[1];

        // Default settings according to discussion in amp-library #4
        $el->setAttribute('width', $width);
        $el->setAttribute('height', $height);
        $el->setAttribute('layout', 'responsive');
    }
}
