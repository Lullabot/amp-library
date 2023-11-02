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

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\UrlSpec;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Utility\ParseUrl;

/**
 * Class ParsedUrlSpec
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ParsedUrlSpec class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/main/validator/validator.js )
 */
class ParsedUrlSpec
{
    /** @var UrlSpec|null */
    protected $spec = null;
    protected $allowed_protocols = []; // Treat as set

    /**
     * ParsedUrlSpec constructor.
     * @param UrlSpec|null $spec
     */
    public function __construct($spec)
    {
        $this->spec = $spec;
        if (!empty($this->spec->allowed_protocol)) {
            /** @var string $allowed_protocol */
            foreach ($this->spec->allowed_protocol as $allowed_protocol) {
                $this->allowed_protocols[$allowed_protocol] = 1; // Treat as a Set
            }
        }
    }

    /**
     * @param Context $context
     * @param string $attr_name
     * @param string $url
     * @param TagSpec $tagspec
     * @param SValidationResult $validation_result
     */
    public function validateUrlAndProtocolInAttr(Context $context, $attr_name, $url, TagSpec $tagspec, SValidationResult $validation_result)
    {
        $this->validateUrlAndProtocol(new ParsedUrlSpecAttrErrorAdapter($attr_name), $context, $url, $tagspec, $validation_result);
    }

    /**
     * @param Context $context
     * @param string $url
     * @param TagSpec $tagspec
     * @param SValidationResult $validation_result
     * @param int $line_delta
     */
    public function validateUrlAndProtocolInStyleSheet(Context $context, $url, TagSpec $tagspec, SValidationResult $validation_result, $line_delta = 0)
    {
        $this->validateUrlAndProtocol(new ParsedUrlSpecStyleSheetErrorAdapter(), $context, $url, $tagspec, $validation_result, $line_delta);
    }

    /**
     * @param ParsedUrlSpecAttrErrorAdapter|ParsedUrlSpecStyleSheetErrorAdapter $adapter
     * @param Context $context
     * @param string $url
     * @param TagSpec $tagspec
     * @param SValidationResult $validation_result
     * @param int $line_delta
     */
    public function validateUrlAndProtocol($adapter, Context $context, $url, TagSpec $tagspec, SValidationResult $validation_result, $line_delta = 0)
    {
        if (empty(trim($url)) && empty($this->spec->allow_empty)) {
            $adapter->missingUrl($context, $tagspec, $validation_result, $line_delta);
            return;
        }

        $url_components = ParseUrl::parse_url($url);
        if ($url_components === false) {
            $adapter->invalidUrl($context, $url, $tagspec, $validation_result, $line_delta);
            return;
        }

        if (!empty($url_components['scheme'])) {
            $scheme = mb_strtolower($url_components['scheme'], 'UTF-8');
            if (!isset($this->allowed_protocols[$scheme])) {
                $adapter->invalidUrlProtocol($context, $scheme, $tagspec, $validation_result, $line_delta);
                return;
            }
        }

        if (empty($this->spec->allow_relative) && empty($url_components['scheme'])) {
            $adapter->disallowedRelativeUrl($context, $url, $tagspec, $validation_result, $line_delta);
            return;
        }

        if (!empty($url_components['host'])) {
            $host = $url_components['host'];
            //  valid host using rfc952 with ipv6 and ipv4 ips and support utf-8 hosts
            if (preg_match("/^(((?:(?:[a-z0-9\\x{00a1}-\\x{ffff}](?:-)*)*(?:[a-z0-9\\x{00a1}-\\x{ffff}])+)(?:\\.(?:[a-z0-9\\x{00a1}-\\x{ffff}](?:-)*)*(?:[a-z0-9\\x{00a1}-\\x{ffff}])+)*(?:\\.(?:[a-z0-9\\x{00a1}-\\x{ffff}]){2,}))|\\[(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))\\]|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))$/u", $host) !== 1) {
                $adapter->invalidUrlHost($context, $host, $tagspec, $validation_result, $line_delta);
                return;
            }
        }
    }

}
