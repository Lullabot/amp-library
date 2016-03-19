<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

class HtmlCommentPass extends BasePass
{
    function pass()
    {
        $comments = $this->q->xpath('//comment()')->get();
        /** @var \DOMNode $comment */
        foreach ($comments as $comment) {
            if (preg_match('/(*UTF8)\[if/i', $comment->textContent) || preg_match('/(*UTF8)\[endif/i', $comment->textContent)) {
                $this->addActionTaken(new ActionTakenLine('HTML conditional comments not allowed.', ActionTakenType::TAG_REMOVED, $comment->getLineNo()));
                $comment->parentNode->removeChild($comment);
            }
        }

        return $this->transformations;
    }
}
