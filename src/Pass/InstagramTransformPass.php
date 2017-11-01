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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use QueryPath\DOMQuery;

use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class InstagramTransformPass
 * @package Lullabot\AMP\Pass
 */
class InstagramTransformPass extends BasePass
{
    const DEFAULT_INSTAGRAM_HEIGHT = 400;
    const DEFAULT_INSTAGRAM_WIDTH = 400;

    function pass()
    {
        $all_instagram = $this->q->find('blockquote.instagram-media');
        /** @var DOMQuery $el */
        foreach ($all_instagram as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $this->getLineNo($dom_el);
            list($shortcode, $url) = $this->getShortcodeAndUrl($el);
            // If we can't get the instagram shortcode, abort
            if (empty($shortcode)) {
                continue;
            }

            $context_string = $this->getContextString($dom_el);
            $instagram_script_tag = $this->getScriptTag($el, '&(*UTF8)instagram\.com/.*/embeds.js&i');

            /** @var \DOMElement $new_dom_el */
            $el->after('<amp-instagram layout="responsive"></amp-instagram>');
            $new_el = $el->next();

            // Set shortcode and use oembed to get the image size parameters
            $error_string = $this->setInstagramShortcodeAndDimensions($new_el, $shortcode, $url);
            // Set caption, if it has.
            $this->setInstagramCaptioned($el, $new_el);

            $new_dom_el = $new_el->get(0);

            // Remove the blockquote, its children and the instagram script tag that follows after the blockquote
            $el->removeChildren()->remove();
            if (!empty($instagram_script_tag)) {
                $instagram_script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('blockquote.instagram-media (with associated script tag)', ActionTakenType::INSTAGRAM_CONVERTED, $lineno, $context_string, $error_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('blockquote.instagram-media', ActionTakenType::INSTAGRAM_CONVERTED, $lineno, $context_string, $error_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->transformations;
    }

    /**
     * Set the shortcode. Using the oembed instagram endpoint, set the instagram height and width attributes
     *
     * @param DOMQuery $el
     * @param string $shortcode
     * @param string $url
     * @return string|null
     */
    protected function setInstagramShortcodeAndDimensions(DOMQuery $el, $shortcode, $url)
    {
        $el->attr('data-shortcode', $shortcode);
        $el->attr('width', self::DEFAULT_INSTAGRAM_WIDTH);
        $el->attr('height', self::DEFAULT_INSTAGRAM_HEIGHT);

        $client = new Client();
        try {
            $res = $client->get('https://api.instagram.com/oembed/', [
                'query' => ['url' => $url]
            ]);
        } catch (GuzzleException $e) {
            return $e->getMessage() . PHP_EOL . 'Could not make request to instagram oembed endpoint. Setting default height and width';
        }

        if ($res->getStatusCode() !== 200) {
            return "Instagram oembed endpoint returned status code: {$res->getStatusCode()} . Setting default height and width.";
        }

        $oembed = json_decode($res->getBody(), true);
        if (empty($oembed)) {
            return "Instagram oembed endpoint returned invalid json. Setting default height and width.";
        }

        if (isset($oembed['thumbnail_width']) && isset($oembed['thumbnail_height'])) {
            $el->attr('width', $oembed['thumbnail_width']);
            $el->attr('height', $oembed['thumbnail_height']);
        }

        return null;
    }

    /**
     * If the instragram to embed has caption, set the instagram caption attribute
     *
     * @param DOMQuery $el
     * @param DOMQuery $new_el
     * @return string|null
     */
    protected function setInstagramCaptioned(DOMQuery $el, DOMQuery $new_el)
    {
        if ($el->hasAttr('data-instgrm-captioned')) {
            $new_el->attr('data-captioned', true);
        }

        return null;
    }

    /**
     * Get instagram shortcode from the instagram embed code
     *
     * @param DOMQuery $el
     * @return array
     */
    protected function getShortcodeAndUrl(DOMQuery $el)
    {
        $links = $el->find('a');
        /** @var DOMQuery $link */
        $shortcode = '';
        $url = '';
        // Get the shortcode from the first <a> tag that matches regex and exit
        foreach ($links as $link) {
            $href = $link->attr('href');
            $matches = [];
            if (preg_match('&(*UTF8)instagram.com/p/([^/]+)/?&i', $href, $matches)) {
                if (!empty($matches[1])) {
                    $shortcode = $matches[1];
                    $url = $href;
                    break;
                }
            }
        }

        return [$shortcode, $url];
    }
}
