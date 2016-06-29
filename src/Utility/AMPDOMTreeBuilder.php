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

namespace Lullabot\AMP\Utility;

use Masterminds\HTML5\Parser\DOMTreeBuilder;
use Masterminds\HTML5\Parser\InputStream;
use Masterminds\HTML5\Parser\Scanner;
use Lullabot\AMP\AMP;

/**
 * Class AMPDOMTreeBuilder
 * @package Lullabot\AMP\Utility
 *
 * Extends the Masterminds\HTML5\Parser\DOMTreeBuilder class
 * @see https://github.com/Masterminds/html5-php/blob/2.x/src/HTML5/Parser/DOMTreeBuilder.php
 *
 * (For masterminds/html5-php project @see https://github.com/Masterminds/html5-php )
 */
class AMPDOMTreeBuilder extends DOMTreeBuilder
{
    /** @var Scanner */
    protected $scanner;

    /**
     * @return Scanner
     */
    public function getEmbeddedScanner()
    {
        return $this->scanner;
    }

    /**
     * AMPDOMTreeBuilder constructor.
     * @param InputStream $inputstream
     * @param array $options
     */
    public function __construct(InputStream $inputstream, array $options = [])
    {
        // We embed a scanner so that $this->startTag() knows the current line number
        $this->scanner = new Scanner($inputstream);
        parent::__construct(false, $options);
    }

    /**
     * This is the function where the main magic happens. Tack on the line number attribute and pass onto the
     * parent::startTag()
     *
     * @param string $name
     * @param array $attributes
     * @param bool $selfClosing
     * @return bool|int
     */
    public function startTag($name, $attributes = [], $selfClosing = false)
    {
        // Add this attribute to every tag so that we know the line number
        $attributes[AMP::AMP_LINENUM_ATTRIBUTE] = $this->scanner->currentLine();
        return parent::startTag($name, $attributes, $selfClosing);
    }
}
