<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

class HtmlCommentPass extends BasePass
{
    function pass()
    {
        $comments = $this->q->xpath('//comment()')->get();
        foreach ($comments as $comment) {
            if (preg_match('/(*UTF8)\[if/i', $comment->textContent) || preg_match('/(*UTF8)\[endif/i', $comment->textContent)) {
                $this->addWarning(new Warning('HTML conditional comments not allowed.', ActionTaken::TAG_REMOVED, $comment->getLineNo()));
                $comment->parentNode->removeChild($comment);
            }
        }

        return $this->warnings;
    }
}
