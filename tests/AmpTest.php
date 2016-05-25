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

/**
 * Class AmpTest
 */
class AmpTest extends PHPUnit_Framework_TestCase
{
    /** @var AMP|null */
    protected $amp = null;

    public function setup()
    {
        $this->amp = new AMP();
    }

    /**
     * @dataProvider filenameProvider
     * @param $test_filename
     * @param $fragment
     * @throws Exception
     */
    public function testFiles($test_filename, $fragment)
    {
        $output = $this->amp->consoleOutput($test_filename, $fragment, true, true);
        $expected_output = file_get_contents("$test_filename.out");
        $expected_output_arr = explode('ORIGINAL HTML', $expected_output);
        $output_arr = explode('ORIGINAL HTML', $output);
        $this->assertEquals($expected_output_arr[0], $output_arr[0]);
        $this->assertEquals($expected_output_arr[1], $output_arr[1]);
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
}
