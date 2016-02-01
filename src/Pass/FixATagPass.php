<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

class FixATagPass extends FixHTMLPass
{
    function pass() {
        $all_a = $this->q->find('a');
        /** @var DOMQuery $match */
        foreach($all_a as $match) {
            $lineno = $match->get()->lineno;
            if (preg_match('/^javascript:/', trim($match->attr('href')))) {
                $this->addWarning(new Warning(WarningType::A_HREF_NO_JAVASCRIPT, ActionTaken::TAG_REMOVED, $lineno));
                $match->remove();
            }
            if ($match->attr('target') && "_blank" == trim($match->attr('target'))) {
                $this->addWarning(new Warning(WarningType::A_TARGET_ONLY_BLANK, ActionTaken::ATTRIBUTE_REMOVED, $lineno));
                $match->removeAttr('target');
            }
        }

        return $this->warnings;
    }
}