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


class ParseUrl
{
    /**
     * The purpose of this static method is to provide a workaround for https://bugs.php.net/bug.php?id=68917 that afflicts
     * some versions of PHP and HHVM. This bug affects the parse_url() function.
     *
     * Basically if there is no scheme _and_ there _is_ a port, parse_url() does not work. An
     * example of such a url would be '//example.com:3000/test.html' (these are called protocol relative URLs)
     *
     * Use this method wherever you would have used parse_url().
     *
     * @see https://github.com/facebook/hhvm/issues/7136#issuecomment-224427428
     * @see https://3v4l.org/fotNt (this link from above issue comment -- thanks!) to see which versions of PHP are affected
     * @see https://github.com/Lullabot/amp-library/issues/86
     *
     * @param string $url
     * @return bool|array|string|null
     */
    static function parse_url($url, $component = -1)
    {
        if (!is_string($url)) {
            return false;
        }

        $trimmed_url = trim($url);
        // If this is a protocol relative url e.g. '//example.com/test.html'
        if (strpos($trimmed_url, '//') === 0) {
            // Add random scheme to workaround https://bugs.php.net/bug.php?id=68917
            $final_url = 'amp-random-workaround-scheme:' . $trimmed_url;
            $added_workaround_scheme = true;
        } else {
            $final_url = $trimmed_url;
            $added_workaround_scheme = false;
        }

        $parsed_components = parse_url($final_url, $component);
        if ($added_workaround_scheme && is_array($parsed_components)) {
            unset($parsed_components['scheme']);
        } elseif ($component === PHP_URL_SCHEME && $added_workaround_scheme) {
            $parsed_components = null;
        }

        return $parsed_components;
    }
}