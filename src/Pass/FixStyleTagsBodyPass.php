<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

/**
 * <style> tags are not allowed in <body>
 * Class FixStyleTagsBodyPass
 * @package Lullabot\AMP\Pass
 */
class FixStyleTagsBodyPass extends FixBasePass
{
    function pass()
    {
        $all_a = $this->q->find('body')->find('style');
        /** @var DOMQuery $tag */
        foreach ($all_a as $tag) {
            $lineno = $tag->get(0)->getLineNo();
            $this->addWarning(new Warning('script', WarningType::STYLE_BODY_NOT_ALLOWED, ActionTaken::TAG_REMOVED, $lineno));
            $tag->remove();
        }

        return $this->warnings;
    }
}
