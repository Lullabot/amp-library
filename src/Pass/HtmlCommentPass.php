<?php

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

class HtmlCommentPass extends BasePass
{
    function pass()
    {
        $comments = $this->q->xpath('//comment()')->get();
        foreach ($comments as $comment) {
            if (preg_match('/(*UTF8)\[if/i', $comment->textContent) || preg_match('/(*UTF8)\[endif/i', $comment->textContent)) {
                $this->addActionTaken(new ActionTakenLine('HTML conditional comments not allowed.', ActionTakenType::TAG_REMOVED, $comment->getLineNo()));
                $comment->parentNode->removeChild($comment);
            }
        }

        return $this->warnings;
    }
}
