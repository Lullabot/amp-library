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

use Lullabot\AMP\AMP;
use Lullabot\AMP\Spec\UrlSpec;
use Lullabot\AMP\Spec\ValidationRulesFactory;
use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Validate\ParsedValidatorRules;
use PHPUnit\Framework\TestCase;

/**
 * Class AmpTest
 */
class AmpTest extends TestCase
{
    /** @var AMP */
    protected $amp = null;
    protected $skip_internet = false;

    public function setup()
    {
        $this->moveImageFixturesToTmp();
        $parsed_rules = $this->getParsedRulesTestSet();
        $this->amp = new AMP($parsed_rules);
        $this->skip_internet = getenv('AMP_TEST_SKIP_INTERNET');
    }

    /**
     * @dataProvider filenameProvider
     * @param $test_filename
     * @param $fragment
     * @throws Exception
     */
    public function testFiles($test_filename, $fragment)
    {
        $options = $this->amp->getOptionsFromStandardOptionFile($test_filename);
        $output = $this->amp->consoleOutput($test_filename, $options, $fragment, true, true);
        $expected_output = $this->getExpectedOutput($test_filename);
        if ($expected_output === false) {
            // An out file does not exist, skip this test
            $this->markTestSkipped("$test_filename.out file does not exist. Skipping test.");
        }

        if (!empty($this->skip_internet) && !empty($options['requires_internet'])) {
            $this->markTestSkipped("Skipping test as it requires internet and AMP_TEST_SKIP_INTERNET environment variable is set.");
        }
        $this->assertEquals($expected_output, $output);
    }

    protected function getExpectedOutput($test_filename)
    {
        $version = explode('.', PHP_VERSION);
        // Check if a specific version for this PHP exists.
        if (file_exists("$test_filename.php{$version[0]}.out")) {
            $filename = "$test_filename.php{$version[0]}.out";
        }
        else {
            $filename = "$test_filename.out";
        }
        return @file_get_contents($filename);
    }

    public function filenameProvider()
    {
        $all_tests = [];
        foreach ($this->getTestFiles('tests/test-data/fragment-html/') as $test_filename) {
            $all_tests[$test_filename] = [$test_filename, false];
        }

        foreach ($this->getTestFiles('tests/test-data/full-html/') as $test_filename) {
            $all_tests[$test_filename] = [$test_filename, true];
        }

        return $all_tests;
    }

    protected function getTestFiles($subdirectory)
    {
        /** @var DirectoryIterator $fileitem */
        foreach (new DirectoryIterator($subdirectory) as $fileitem) {
            if (!$fileitem->isFile()) {
                continue;
            }

            $file_pathname = $fileitem->getPathname();
            if (preg_match('/\.html$/', $file_pathname)) {
                yield $file_pathname;
            }
        }
    }

    protected function getTestImages($subdirectory)
    {
        /** @var DirectoryIterator $fileitem */
        foreach (new DirectoryIterator($subdirectory) as $fileitem) {
            if (!$fileitem->isFile() || $fileitem->isDot()) {
                continue;
            }

            yield $fileitem->getPathname();
        }
    }

    protected function allowFileProtocolForImages(ValidatorRules $rules)
    {
        /** @var \Lullabot\AMP\Spec\TagSpec $tag */
        foreach ($rules->tags as $tag) {
            if (!in_array($tag->tag_name, ['img', 'amp-pixel'])) {
                continue;
            }
            /** @var \Lullabot\AMP\Spec\AttrSpec $attr */
            foreach ($tag->attrs as $attr) {
                if ($attr->name !== 'src') {
                    continue;
                }
                $url_spec = new UrlSpec();
                if ($tag->tag_name === 'img') {
                    $url_spec->allowed_protocol = [
                      'data',
                      'http',
                      'https',
                      'file'
                    ];
                }
                elseif ($tag->tag_name === 'amp-pixel') {
                    $url_spec->allowed_protocol = [
                      'https',
                      'file'
                    ];
                }
                $url_spec->allow_relative = TRUE;
                $attr->value_url = $url_spec;
            }
        }

        /** @var \Lullabot\AMP\Spec\AttrList $attr_list */
        foreach ($rules->attr_lists as $attr_list) {
            if ($attr_list->name !== 'mandatory-src-or-srcset') {
                continue;
            }
            /** @var \Lullabot\AMP\Spec\AttrSpec $attr */
            foreach ($attr_list->attrs as $attr) {
                if ($attr->name !== 'src') {
                    continue;
                }
                $url_spec = new UrlSpec();
                $url_spec->allowed_protocol = ['data', 'http', 'https', 'file'];
                $url_spec->allow_relative = TRUE;
                $attr->value_url = $url_spec;
            }
        }
    }

    protected function getParsedRulesTestSet()
    {
        /** @var \Lullabot\AMP\Spec\ValidatorRules $rules */
        $rules = ValidationRulesFactory::createValidationRules();
        $this->allowFileProtocolForImages($rules);
        return ParsedValidatorRules::createParsedValidatorRulesFromValidatorRules($rules);
    }

    protected function moveImageFixturesToTmp()
    {
        foreach ($this->getTestImages('tests/test-data/images') as $image) {
            $path_info = pathinfo($image);
            copy($image, sys_get_temp_dir() . '/' . $path_info['basename']);
        }
    }

}
