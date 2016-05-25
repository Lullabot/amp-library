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

class AMPDOMTreeBuilder extends DOMTreeBuilder
{
    /** @var Scanner */
    protected $scanner;

    public function getEmbeddedScanner() {
        return $this->scanner;
    }

    public function __construct(InputStream $inputstream, array $options = [])
    {
        // We embed a scanner so that $this->startTag() knows the current line number
        $this->scanner = new Scanner($inputstream);
        parent::__construct(false, $options);
    }

    public function startTag($name, $attributes = [], $selfClosing = false)
    {
        // Add this attribute to every tag so what we know the line number
        $attributes['data-amp-library-linenum'] = $this->scanner->currentLine();
        parent::startTag($name, $attributes, $selfClosing);
    }
}
