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

/**
 * Class StatisticsPass
 * @package Lullabot\AMP\Pass
 */
class StatisticsPass extends BasePass
{
    function pass()
    {
        $html_tag = $this->q->find($this->context->getErrorScope());

        // If we don't want statistics or couldn't find tag exit
        if (empty($this->options['add_stats_html_comment']) || empty($html_tag->count())) {
            return [];
        }

        /** @var \DOMElement $html_tag_dom_el */
        $html_tag_dom_el = $html_tag->get(0);
        $stats_data = $this->context->getStatsData();
        $start_time = $stats_data['start_time'];

        $comment_start = "#AMP-START-PLACEHOLDER-$start_time#";
        $this->addComment($comment_start, $html_tag_dom_el, true);

        $comment_end = "#AMP-END-PLACEHOLDER-$start_time#";
        $this->addComment($comment_end, $html_tag_dom_el);

        return [];
    }

    /**
     * Inserts an HTML comment in the DOM
     * If first_child is true then makes the comment the first child of the dom_el otherwise makes it the last child
     *
     * @param $text
     * @param \DOMNode $dom_el
     * @param bool $first_child
     */
    public function addComment($text, \DOMNode $dom_el, $first_child = false)
    {
        $comment = $dom_el->ownerDocument->createComment($text);
        if ($first_child) {
            if ($dom_el->hasChildNodes()) {
                $dom_el->insertBefore($comment, $dom_el->firstChild);
            } else {
                $dom_el->appendChild($comment);
            }
        } else {
            $dom_el->appendChild($comment);
        }
    }
}
