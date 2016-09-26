<?php

namespace Lullabot\AMP\Pass;

class SugarStandardFixPass extends StandardFixPass {
    public function pass() {
        $all_ampad = $this->q->top()->find('amp-ad');
        if ($all_ampad->length > 0) {
            $this->addComponentJsToHead('amp-ad');
        }
        return parent::pass();
    }
}
