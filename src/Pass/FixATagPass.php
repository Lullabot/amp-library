<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

class FixATagPass extends FixHTMLPass
{
    function pass()
    {
        $all_a = $this->q->find('a');
        /** @var DOMQuery $tag */
        foreach ($all_a as $tag) {
            $lineno = $tag->get(0)->getLineNo();
            if (preg_match('/^javascript:/', trim($tag->attr('href')))) {
                $this->addWarning(new Warning('a.href', WarningType::A_HREF_NO_JAVASCRIPT, ActionTaken::TAG_REMOVED, $lineno));
                $tag->remove();
            }
            if ($tag->attr('target') && "_blank" !== trim($tag->attr('target'))) {
                $this->addWarning(new Warning('a.target', WarningType::A_TARGET_ONLY_BLANK, ActionTaken::ATTRIBUTE_REMOVED, $lineno));
                $tag->removeAttr('target');
            }
        }

        return $this->warnings;
    }
}