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

use Masterminds\HTML5\Parser\Tokenizer;

/**
 * Class AMPTokenizer
 * @package Lullabot\AMP\Utility
 *
 * Extends the Masterminds\HTML5\Parser\Tokenizer class, passing along the embedded scanner in the AMPDOMTreeBuilder to it
 * @see https://github.com/Masterminds/html5-php/blob/2.x/src/HTML5/Parser/Tokenizer.php
 *
 * (For masterminds/html5-php project @see https://github.com/Masterminds/html5-php )
 */
class AMPTokenizer extends Tokenizer
{
    /**
     * AMPTokenizer constructor.
     * @param AMPDOMTreeBuilder $amp_tree_builder
     */
    public function __construct(AMPDOMTreeBuilder $amp_tree_builder)
    {
        parent::__construct($amp_tree_builder->getEmbeddedScanner(), $amp_tree_builder);
    }
}
