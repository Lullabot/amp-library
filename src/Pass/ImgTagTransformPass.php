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

use Lullabot\AMP\Utility\ParseUrl;
use Lullabot\AMP\Validate\CssLengthAndUnit;
use Lullabot\AMP\Validate\GroupedValidationResult;
use Lullabot\AMP\Validate\Scope;
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Validate\ParsedValidatorRules;

use FastImageSize\FastImageSize;
use QueryPath\DOMQuery;


/**
 * Class ImgTagTransformPass
 * @package Lullabot\AMP\Pass
 *
 * Transform all <img> tags which don't have noscript as an ancestor to <amp-img> tags
 * - height and width are obtained by trying to look at the image file itself via getimagesize()
 * - Currently the layout is set to responsive
 *
 * This pass also makes sure to insert amp attribute in an html tag. See ImgTagTransformPass::pass() method.
 */
class ImgTagTransformPass extends BasePass
{
    /**
     * @var FastImageSize
     */
    protected $fastimage;

    function __construct(DOMQuery $q, Context $context, SValidationResult $validation_result, GroupedValidationResult $grouped_validation_result, ParsedValidatorRules $parsed_rules, array $options)
    {
        $this->fastimage = new FastImageSize();
        parent::__construct($q, $context, $validation_result, $grouped_validation_result, $parsed_rules, $options);
    }

    function pass()
    {
        // Always make sure we do this. Somewhat of a hack
        if ($this->context->getErrorScope() == Scope::HTML_SCOPE) {
            $this->q->find('html')->attr('amp', '');
        }

        $all_a = $this->q->top()->find('img:not(noscript img)');
        /** @var DOMQuery $el */
        foreach ($all_a as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            if ($this->isSvg($dom_el)) {
                // @TODO This should be marked as a validation warning later?
                continue;
            }
            $lineno = $this->getLineNo($dom_el);
            $context_string = $this->getContextString($dom_el);
            $has_height_and_width = $this->setResponsiveImgHeightAndWidth($el);
            if (!$has_height_and_width) {
                $this->addActionTaken(new ActionTakenLine('img', ActionTakenType::IMG_COULD_NOT_BE_CONVERTED, $lineno, $context_string));
                continue;
            }
            if ($this->isPixel($el)) {
                $new_dom_el = $this->convertAmpPixel($el, $lineno, $context_string);
            } else if (!empty($this->options['use_amp_anim_tag']) && $this->isAnimatedImg($dom_el)) {
                $new_dom_el = $this->convertAmpAnim($el, $lineno, $context_string);
            } else {
                $new_dom_el = $this->convertAmpImg($el, $lineno, $context_string);
            }
            $this->context->addLineAssociation($new_dom_el, $lineno);
            $el->remove(); // remove the old img tag
        }

        return $this->transformations;
    }

    /**
     * Given an image element returns an amp-pixel element with the same source
     *
     * @param DOMQuery $el
     * @param int $lineno
     * @param string $context_string
     * @return DOMElement
     */
    protected function convertAmpPixel($el, $lineno, $context_string)
    {
        $dom_el = $el->get(0);
        $new_dom_el = $dom_el->ownerDocument->createElement('amp-pixel');
        $src = $el->attr('src');
        if (strpos($src, 'http://') !== false) {
            $src = str_replace('http://', 'https://', $src);
        }
        $new_dom_el->setAttribute('src', $src);
        $dom_el->parentNode->insertBefore($new_dom_el, $dom_el);
        $this->addActionTaken(new ActionTakenLine('img', ActionTakenType::IMG_PIXEL_CONVERTED, $lineno, $context_string));
        return $new_dom_el;
    }

    /**
     * Given an image element returns an amp-img element with the same attributes and children
     *
     * @param DOMQuery $el
     * @param int $lineno
     * @param string $context_string
     * @return DOMElement
     */
    protected function convertAmpImg($el, $lineno, $context_string)
    {
        $dom_el = $el->get(0);
        $new_dom_el = $this->cloneAndRenameDomElement($dom_el, 'amp-img');
        $new_el = $el->prev();
        $this->setLayoutIfNoLayout($new_el, $this->getLayout($el));
        $this->addActionTaken(new ActionTakenLine('img', ActionTakenType::IMG_CONVERTED, $lineno, $context_string));
        return $new_dom_el;
    }

    /**
     * Given an image DOMQuery
     * Returns whether the image should have 'fixed' or 'responsive' layout
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getLayout($el) {
        return (isset($this->options['img_max_fixed_layout_width'])
            && $this->options['img_max_fixed_layout_width'] >= $el->attr('width'))
            ? 'fixed' : 'responsive';
    }

    /**
     * Given an animated image element returns an amp-anim element with the same attributes and children
     *
     * @param DOMQuery $el
     * @param int $lineno
     * @param string $context_string
     * @return DOMElement
     */
    protected function convertAmpAnim($el, $lineno, $context_string)
    {
        $dom_el = $el->get(0);
        $new_dom_el = $this->cloneAndRenameDomElement($dom_el, 'amp-anim');
        $new_el = $el->prev();
        $this->setLayoutIfNoLayout($new_el, 'responsive');
        $this->addActionTaken(new ActionTakenLine('img', ActionTakenType::IMG_ANIM_CONVERTED, $lineno, $context_string));
        return $new_dom_el;
    }

    /**
     * Given an image src attribute, try to get its dimensions
     * Returns false on failure
     *
     * @param string $src
     * @return bool|array
     */
    protected function getImageWidthHeight($src)
    {
        $img_url = $this->getImageUrl($src);

        if ($img_url === false) {
            return false;
        }

        // Try obtaining image size without having to download the whole image
        $size = $this->fastimage->getImageSize($img_url);
        return $size;
    }

    /**
     * Detects if the img is a SVG. In that case we simply try to skip conversion.
     * @param \DOMElement $el
     * @return bool
     */
    protected function isSvg(\DOMElement $el)
    {
        if (!$el->hasAttribute('src')) {
            return false;
        }

        $src = trim($el->getAttribute('src'));
        if (preg_match('/.*\.svg$/', $src)) {
            return true;
        }

        return false;
    }

    /**
     * Detects if the img is a 1x1 pixel. In that case we convert to <amp-pixel> instead of <amp-img>
     * @param DOMQuery $el
     * @return bool
     */
    protected function isPixel(DOMQuery $el)
    {
        return $el->attr('width') === '1' && $el->attr('height') === '1';
    }

    /**
     * Detects if the img is animated. In that case we convert to <amp-anim> instead of <amp-img>
     * @param \DOMElement $el
     * @return bool
     */
    protected function isAnimatedImg(\DOMElement $el)
    {
        $animated_type = ['gif', 'png'];
        if (!$el->hasAttribute('src')) {
            return true;
        }

        $src = trim($el->getAttribute('src'));
        if (preg_match('/\.([a-z0-9]+)$/i', parse_url($src,PHP_URL_PATH), $match)) {
            if (!empty($match[1]) && in_array(strtolower($match[1]), $animated_type)) {
                if ($match[1] === "gif") {
                    if ($this->isAnimatedGif($src)) {
                        return true;
                    } else {
                        return false;
                    }
                }
                if ($this->isApng($src)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Identifies APNGs
     * Written by Coda, functionified by Foone/Popcorn Mariachi#!9i78bPeIxI
     * This code is in the public domain
     *
     * @see http://stackoverflow.com/a/4525194
     * @see http://foone.org/apng/identify_apng.php
     *
     * @param  string  $src    The filename
     * @return bool    true if the file is an APMG
     */
    function isApng($src)
    {
        $img_bytes = @file_get_contents($src);
        if ($img_bytes) {
            if (strpos(substr($img_bytes, 0, strpos($img_bytes, 'IDAT')), 'acTL') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detects if the gif image is animated or not
     * source: http://php.net/manual/en/function.imagecreatefromgif.php#104473
     *
     * @param  string  $filename
     * @return bool
     */
    function isAnimatedGif($filename) {
        if (!($fh = @fopen($filename, 'rb')))
            return FALSE;
        $count = 0;
        //an animated gif contains multiple "frames", with each frame having a
        //header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
       }

        fclose($fh);
        return $count > 1;
    }

    /**
     * @param string $src
     * @return boolean|string
     */
    protected function getImageUrl($src)
    {
        $src = trim($src);
        $urlc = ParseUrl::parse_url($src);
        // If there is a host, path and optional scheme FastImage can simply try that URL
        if (!empty($urlc['host']) && !empty($urlc['path'])) {
            if (empty($urlc['scheme'])) {
                // There will always be a value for $this->options['request_scheme']
                $src = $this->options['request_scheme'] . $src;
            }
        } else if (!empty($urlc['path'])) {
            // Is there a leading '/' then the path is absolute. Simply prefix the server url
            if (strpos($urlc['path'], '/') === 0 && !empty($this->options['server_url'])) {
                $src = $this->options['server_url'] . $urlc['path'];
            } else if (!empty($this->options['base_url_for_relative_path'])) {
                $src = $this->options['base_url_for_relative_path'] . $urlc['path'];
            } else {
                $src = false;
            }
        } else {
            $src = false;
        }

        return $src;
    }

    /**
     * @param DOMQuery $el
     * @return bool
     */
    protected function setResponsiveImgHeightAndWidth(DOMQuery $el)
    {
        // Static cache
        static $image_dimensions_cache = [];

        $wcss = new CssLengthAndUnit($el->attr('width'), false);
        $hcss = new CssLengthAndUnit($el->attr('height'), false);

        if ($wcss->is_set && $wcss->is_valid && $hcss->is_set && $hcss->is_valid && $wcss->unit == $hcss->unit) {
            return true;
        }

        $src = trim($el->attr('src'));
        if (empty($src)) {
            return false;
        }

        if (isset($image_dimensions_cache[$src])) {
            $dimensions = $image_dimensions_cache[$src];
        } else {
            $dimensions = $this->getImageWidthHeight($src);
        }

        if ($dimensions !== false) {
            $image_dimensions_cache[$src] = $dimensions;
            $el->attr('width', $dimensions['width']);
            $el->attr('height', $dimensions['height']);
            return true;
        } else {
            return false;
        }
    }
}
