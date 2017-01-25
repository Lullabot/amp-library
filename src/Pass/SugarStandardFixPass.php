<?php

namespace Lullabot\AMP\Pass;

class SugarStandardFixPass extends StandardFixPass {
    public function pass() {
        // amp-ad and amp-anim are special cases
        // because amp-ad is not a converted tag
        // and amp-anim is converted from img, which also corresponds to amp-img.
        // Other required components are added in parent::pass()
        foreach (['amp-ad', 'amp-embed', 'amp-anim', 'amp-video'] as $tag) {
            $all_tags = $this->q->top()->find($tag);
            if ($all_tags->length > 0) {
                $this->context->addComponent($tag);
            }
        }
        return parent::pass();
    }
}
