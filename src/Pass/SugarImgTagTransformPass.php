<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Validate\Scope;
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

use QueryPath\DOMQuery;

/**
 * Class SugarImgTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * See docs for ImgTagTransformPass
 * Additionally: amp-pixel and fixed layout for small images
 */
class SugarImgTagTransformPass extends ImgTagTransformPass
{
    function pass()
    {
        // Always make sure we do this. Somewhat of a hack
        if ($this->context->getErrorScope() == Scope::HTML_SCOPE) {
            $this->q->find('html')->attr('amp', '');
        }

        $all_a = $this->q->top()->find('img:not(noscript img)');
        /** @var DOMQuery $el */
        foreach ($all_a as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            if ($this->isSvg($dom_el)) {
                // @TODO This should be marked as a validation warning later?
                continue;
            }
            $context_string = $this->getContextString($dom_el);
            $this->setResponsiveImgHeightAndWidth($el);
            if ($el->attr('width') === '1' && $el->attr('height') === '1') {
                // Convert 1x1 images to amp-pixel tracking tags
                $new_dom_el = $dom_el->ownerDocument->createElement('amp-pixel');
                $new_dom_el->setAttribute('src', $el->attr('src'));
                $dom_el->parentNode->insertBefore($new_dom_el, $dom_el);
                $success_action_taken_type = ActionTakenType::IMG_PIXEL_CONVERTED;
            }
            else {
                // Convert gif images to amp-anim, other images to amp-img
                $pathinfo = pathinfo($el->attr('src'));

                if ($pathinfo['extension'] == 'gif') {
                    $amp_tag = 'amp-anim';
                    $success_action_taken_type = ActionTakenType::IMG_ANIM_CONVERTED;
                    $fail_action_taken_type = ActionTakenType::IMG_ANIM_COULD_NOT_BE_CONVERTED;
                }
                else {
                    $amp_tag = 'amp-img';
                    $success_action_taken_type = ActionTakenType::IMG_CONVERTED;
                    $fail_action_taken_type = ActionTakenType::IMG_COULD_NOT_BE_CONVERTED;
                }

                $new_dom_el = $this->cloneAndRenameDomElement($dom_el, $amp_tag);
                $new_el = $el->prev();

                $success = $this->setResponsiveImgHeightAndWidth($new_el);
                // We were not able to get the image dimensions, abort conversion.
                if (!$success) {
                    $this->addActionTaken(new ActionTakenLine('img', $fail_action_taken_type, $lineno, $context_string));
                    // Abort the conversion and remove the new img tag
                    $new_el->remove();
                    continue;
                }

                $layout = ($new_el->attr('width') < 300 && $new_el->attr('width') < 300)
                    ? 'fixed' : 'responsive';

                $this->setLayoutIfNoLayout($new_el, $layout);
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
            $this->addActionTaken(new ActionTakenLine('img', $success_action_taken_type, $lineno, $context_string));
            $el->remove(); // remove the old img tag
        }

        return $this->transformations;
    }
}
