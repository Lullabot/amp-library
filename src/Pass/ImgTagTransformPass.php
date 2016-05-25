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
 * This pass also make sure to insert amp attribute in an html tag. See ImgTagTransformPass::pass().
 */
class ImgTagTransformPass extends BasePass
{
    /**
     * @var FastImageSize
     */
    protected $fastimage;

    function __construct(DOMQuery $q, Context $context, SValidationResult $validation_result, ParsedValidatorRules $parsed_rules, array $options)
    {
        $this->fastimage = new FastImageSize();
        parent::__construct($q, $context, $validation_result, $parsed_rules, $options);
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
            $lineno = $this->getLineNo($dom_el);
            if ($this->isSvg($dom_el)) {
                // @TODO This should be marked as a validation warning later?
                continue;
            }
            $context_string = $this->getContextString($dom_el);
            $new_dom_el = $this->cloneAndRenameDomElement($dom_el, 'amp-img');
            $el->remove(); // remove the old img tag

            $this->setAmpImgAttributes($new_dom_el);
            $this->context->addLineAssociation($new_dom_el, $lineno);
            $this->addActionTaken(new ActionTakenLine('img', ActionTakenType::IMG_CONVERTED, $lineno, $context_string));
        }

        return $this->transformations;
    }

    /**
     * Given an image src attribute, try to get its dimensions
     * Returns false on failure
     *
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
    protected function isSvg(\DOMElement $el) {
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
     * @param string $src
     * @return boolean|string
     */
    protected function getImageUrl($src)
    {
        $src = trim($src);
        $urlc = parse_url($src);
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

    protected function setAmpImgAttributes(\DOMElement $el)
    {
        // If height or image is not set, get it from the image
        if (!$el->getAttribute('width') || !$el->getAttribute('height')) {
            $dimensions = $this->getImageWidthHeight($el->getAttribute('src'));
            if ($dimensions !== false) {
                $el->setAttribute('width', $dimensions['width']);
                $el->setAttribute('height', $dimensions['height']);
            }
        }

        // Sane default for now
        if (!$el->hasAttribute('layout')) {
            $el->setAttribute('layout', 'responsive');
        }
    }
}
