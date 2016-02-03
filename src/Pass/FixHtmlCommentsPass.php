<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

class FixHtmlCommentsPass extends FixBasePass
{
    function pass()
    {
        $comments = $this->q->xpath('//comment()')->get();
        foreach ($comments as $comment) {
            if (preg_match('/\[if/i', $comment->textContent) || preg_match('/\[endif/i', $comment->textContent)) {
                $this->addWarning(new Warning('HTML comment', WarningType::COMMENT_CONDITIONAL_NOT_ALLOWED, ActionTaken::TAG_REMOVED, $comment->getLineNo()));
                $comment->parentNode->removeChild($comment);
            }
        }

        return $this->warnings;
    }
}
