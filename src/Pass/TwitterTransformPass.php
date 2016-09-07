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

use QueryPath\DOMQuery;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class TwitterTransformPass
 * @package Lullabot\AMP\Pass
 */
class TwitterTransformPass extends BasePass
{
    function pass()
    {
        $all_tweets = $this->q->top()->find('blockquote.twitter-tweet');
        /** @var DOMQuery $el */
        foreach ($all_tweets as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);

            $tweet_id = $this->getTweetId($el);
            // Very important, if we didn't find a tweet id then go to next tweet
            // This could be just a simple blockquote
            if (empty($tweet_id)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);

            // Get reference to associated <script> tag, if any.
            $twitter_script_tag = $this->getScriptTag($el, '&(*UTF8)twitter.com/widgets\.js&i');
            $tweet_attributes = $this->getTweetAttributes($el);

            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-twitter $tweet_attributes layout=\"responsive\" data-tweetid=\"$tweet_id\"></amp-twitter>");
            $new_dom_el = $el->get(0);

            // Remove the blockquote, its children and the twitter script tag that follows after the blockquote
            $el->removeChildren()->remove();
            if (!empty($twitter_script_tag)) {
                $twitter_script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('blockquote.twitter-tweet (with twitter script tag)', ActionTakenType::TWITTER_CONVERTED, $lineno, $context_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('blockquote.twitter-tweet', ActionTakenType::TWITTER_CONVERTED, $lineno, $context_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * Get some extra attributes from the blockquote such as data-cards, data-conversation etc.
     * @see https://dev.twitter.com/web/javascript/creating-widgets#create-tweet
     *
     * If data-cards=hidden for instance, photos are now shown with the tweet
     * If data-conversation=none for instance, no conversation is shown in the tweet
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getTweetAttributes(DOMQuery $el)
    {
        $tweet_attributes = '';
        $height_exists = false;
        $width_exists = false;
        $data_cards_hidden = false;

        foreach ($el->attr() as $attr_name => $attr_value) {
            if (mb_strpos($attr_name, 'data-', 0, 'UTF-8') !== 0 && !in_array($attr_name, ['width', 'height'])) {
                continue;
            }

            if ($attr_name == 'height') {
                $height_exists = true;
            }

            if ($attr_name == 'width') {
                $width_exists = true;
            }

            if ($attr_name == 'data-cards' && trim($attr_value) == 'hidden') {
                $data_cards_hidden = true;
            }

            $tweet_attributes .= " $attr_name = \"$attr_value\"";
        }

        // Dealing with height and width is going to be tricky
        // https://github.com/ampproject/amphtml/blob/master/extensions/amp-twitter/amp-twitter.md
        // @todo make this smarter
        // Twitter js widget should make it look fine
        if (!$height_exists) {
            // A sensible default
            if ($data_cards_hidden) {
                $tweet_attributes .= ' height = "223" ';
            } else {
                $tweet_attributes .= ' height = "694" ';
            }
        }
        // Twitter js widget should make it look fine
        if (!$width_exists) {
            // A sensible default
            $tweet_attributes .= ' width = "486" ';
        }
        // end height width hack

        return $tweet_attributes;
    }

    /**
     * Get twitter status from the twitter embed code
     */
    protected function getTweetId(DOMQuery $el)
    {
        $links = $el->find('a');
        /** @var DOMQuery $link */
        $tweet_id = '';
        // Get the shortcode from the first <a> tag that matches regex and exit
        foreach ($links as $link) {
            $href = $link->attr('href');
            $matches = [];
            if (preg_match('&(*UTF8)twitter.com/.*/status(?:es)?/([^/]+)&i', $href, $matches)) {
                if (!empty($matches[1])) {
                    $tweet_id = $matches[1];
                    break;
                }
            }
        }

        return $tweet_id;
    }
}
