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

namespace Lullabot\AMP;

use QueryPath;
use SebastianBergmann\Diff\Differ;
use Lullabot\AMP\Pass\BasePass;
use Lullabot\AMP\Spec\ValidatorRules;
use Lullabot\AMP\Validate\ParsedValidatorRules;
use Lullabot\AMP\Validate\Scope;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Validate\RenderValidationResult;
use Lullabot\AMP\Utility\ActionTakenLine;


/**
 * Class AMP
 * @package Lullabot\AMP
 *
 * This is the main end user facing class.
 * All validation and HTML fixing functionality is controlled from here
 */
class AMP
{
    // The StandardScanPass should be first after all transform passes
    // The StandardFixPass should be after StandardScanPass
    public $passes = [
        'Lullabot\AMP\Pass\ImgTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\IframeSoundCloudTagTransformPass', // Transform Pass
        'Lullabot\AMP\Pass\IframeVimeoTagTransformPass', // Transform Pass
        'Lullabot\AMP\Pass\IframeVineTagTransformPass', // Transform Pass
        'Lullabot\AMP\Pass\IframeDailymotionTagTransformPass', // Transform Pass
        'Lullabot\AMP\Pass\IframeYouTubeTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\IframeTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\InstagramTransformPass', // Transform pass
        'Lullabot\AMP\Pass\PinterestTagTransformPass', // Transform pass
        'Lullabot\AMP\Pass\TwitterTransformPass', // Transform pass
        'Lullabot\AMP\Pass\StandardScanPass',
        'Lullabot\AMP\Pass\StandardFixPass',
        'Lullabot\AMP\Pass\StatisticsPass'
    ];

    /** @var ActionTakenLine[] */
    protected $action_taken = [];
    /** @var string */
    protected $input_html = '';
    /** @var string */
    protected $amp_html = '';
    /** @var ValidatorRules */
    protected $rules;
    /** @var ParsedValidatorRules */
    protected $parsed_rules;
    /** @var Context */
    protected $context = null;
    /** @var  SValidationResult */
    protected $validation_result = null;
    /** @var string */
    protected $scope = Scope::BODY_SCOPE;
    /** @var string[] */
    protected $component_js = [];
    /** @var array */
    protected $options;

    public function getComponentJs()
    {
        return $this->component_js;
    }

    /**
     * @deprecated use getActionTaken
     * @return array
     */
    public function getWarnings()
    {
        return $this->action_taken;
    }

    public function getActionTaken()
    {
        return $this->action_taken;
    }

    public function getInputHtml()
    {
        return $this->input_html;
    }

    public function getAmpHtml()
    {
        return $this->amp_html;
    }

    /**
     * AMP constructor.
     *
     * @see src/Spec/validator-generated.php
     */
    public function __construct()
    {
        // The ParsedValidationRules object is expensive to create. So we maintain a global singleton
        // This way the AMP Object creation is actually cheap
        /** @var ParsedValidatorRules parsed_rules */
        $this->parsed_rules = ParsedValidatorRules::getSingletonParsedValidatorRules();
        /** @var ValidatorRules rules */
        $this->rules = $this->parsed_rules->rules;
    }

    /**
     * Calling this function "clears" the state of the AMP object.
     * It then "loads" up new HTML, that is ready for conversion with
     * AMP::convertToAmpHtml()
     *
     * @param string $html
     * @param array $options
     * @throws \Exception
     */
    public function loadHtml($html, $options = [])
    {
        $this->clear();
        // Deal with with some edge cases
        // Ideally we should just throw an exception if we're not passed a string but we can be a bit liberal for now
        if (is_null($html)) {
            $this->input_html = '';
        } else if (!is_string($html)) {
            // Try to convert it it to string
            $this->input_html = (string)$html;
        } else {
            // This is the main case
            $this->input_html = $html;
        }

        // Does the user want a statistics (peak memory, time taken, time generated etc) comment at the end?
        if (empty($options['add_stats_html_comment'])) {
            $options['add_stats_html_comment'] = false;
        } else {
            $options['add_stats_html_comment'] = true;
        }

        // By default the html5 parser is enabled
        if (!isset($options['use_html5_parser'])) {
            $options['use_html5_parser'] = true;
        }

        $this->options = $options;
        $this->scope = !empty($options['scope']) ? $options['scope'] : Scope::BODY_SCOPE;

        // Currently we only support these two scopes
        if (!in_array($this->scope, [Scope::HTML_SCOPE, Scope::BODY_SCOPE])) {
            throw new \Exception("Invalid or currently unsupported scope $this->scope");
        }
        $this->context = new Context($this->scope);

        // Get the request scheme http, https etc.
        if (empty($options['request_scheme'])) {
            if (!empty($_SERVER['https'])) {
                $this->options['request_scheme'] = 'https://';
            } else {
                $this->options['request_scheme'] = 'http://';
            }
        }

        // What is the server url e.g. http://www.cnn.com (note no trailing /)
        if (empty($options['server_url']) && !empty($_SERVER['SERVER_NAME'])) {
            $server_url = $this->options['request_scheme'] . $_SERVER['SERVER_NAME'];
            if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80') {
                $server_url .= ':' . $_SERVER['SERVER_PORT'];
            }
            $this->options['server_url'] = $server_url;
        }

        // What is the base relative directory. For http://www.cnn.com/abc/zyz?1234 it is http://www.cnn.com/abc/
        if (empty($this->options['base_url_for_relative_path']) && !empty($_SERVER['REQUEST_URI'])) {
            $matches = [];
            $full_url = $this->options['server_url'] . $_SERVER['REQUEST_URI'];
            if (preg_match('&(.*/)&', $full_url, $matches)) {
                $this->options['base_url_for_relative_path'] = $matches[1];
            }
        }
    }

    /**
     * Calling this function "clears" the state of the AMP object and puts it into default mode
     * Call this function when you don't want anything remaining in the AMP Object.
     *
     * Calling this function is optional.
     */
    public function clear()
    {
        $this->input_html = '';
        $this->action_taken = [];
        $this->amp_html = '';
        $this->options = [];
        $this->component_js = [];
        $this->validation_result = new SValidationResult();
        $this->validation_result->status = ValidationResultStatus::FAIL;
        $this->context = null;
        $this->scope = Scope::BODY_SCOPE;
    }

    /**
     * An HTML fragment is anything that can occur within a _body_ tag
     * This method makes an HTML fragment a full HTML document
     *
     * We need to do this to avoid encoding issues.
     * see https://github.com/technosophos/querypath/issues/94#issuecomment-8784564
     *
     * @param $body_fragment
     * @return string
     */
    protected function makeFragmentWhole($body_fragment)
    {
        $pre_html = '<!DOCTYPE html><html amp><head><meta charset="UTF-8"></head><body>';
        $post_html = '</body></html>';
        return $pre_html . $body_fragment . $post_html;
    }

    /**
     * Provide a bare HTML document
     * @param string
     * @return string
     */
    protected function bareDocument($insert)
    {
        $trimmed = trim($insert);
        if (empty($trimmed)) {
            $html = "<!DOCTYPE html><html></html>";
        } else {
            $html = "<!DOCTYPE html><html><body>$insert</body></html>";
        }
        return $html;
    }

    /**
     * @param string $html
     * @return string
     */
    protected function substituteStatisticsPlaceholders($html)
    {
        if ($this->context->getErrorScope() == Scope::BODY_SCOPE) {
            $scope_text = 'HTML fragment';
        } else {
            $scope_text = 'Full HTML document';
        }

        $stats_data = $this->context->getStatsData();
        $end_time = microtime(true);
        $time_taken = sprintf('%.3f milliseconds (1 second = 1000 milliseconds)', 1000 * ($end_time - $stats_data['start_time']));
        $date = date(DATE_RFC2822);
        $num_tags_processed = $this->context->getNumTagsProcessed();

        // $start_memory_str = sprintf('%.3f MiB', $stats_data['start_memory'] / 1000000);
        $start_memory_peak_str = sprintf('%.3f MiB', $stats_data['start_memory_peak'] / 1000000.0);

        // $end_memory = memory_get_usage();
        // $end_memory_str = sprintf('%.3f MiB', $end_memory / 1000000);

        $end_memory_peak = memory_get_peak_usage();
        $end_memory_peak_str = sprintf('%.3f MiB', $end_memory_peak / 1000000.0);
        $peak_change = ($end_memory_peak == $stats_data['start_memory_peak']) ? '(unchanged)' : '';

        $comment_start = " ==START== $scope_text processed by AMP PHP Library (https://github.com/Lullabot/amp-library) at $date";
        $comment_end = " $scope_text processed by AMP PHP Library (https://github.com/Lullabot/amp-library) at $date" . PHP_EOL
            . " Time Taken: $time_taken" . PHP_EOL
            . " Number of html tags processed: $num_tags_processed" . PHP_EOL
            . " PHP Peak memory usage before calling convertToAmpHtml: $start_memory_peak_str" . PHP_EOL
            . " PHP Peak memory usage at the end of  convertToAmpHtml: $end_memory_peak_str $peak_change" . PHP_EOL
            . " * Please note that time taken will increase significantly if you don't have opcache enabled or have XDEBUG enabled." . PHP_EOL
            . "   Also note that the library downloads initial portions of images to determine dimensions for amp-img tags. " . PHP_EOL
            . "   If your network is slow, your library processing time will increase and network download time may dominate total time taken for library processing." . PHP_EOL
            . "==END==";

        $start_replaced = str_replace("#AMP-START-PLACEHOLDER-${stats_data['start_time']}#", $comment_start, $html);
        $end_replaced = str_replace("#AMP-END-PLACEHOLDER-${stats_data['start_time']}#", $comment_end, $start_replaced);

        return $end_replaced;
    }

    /**
     * Convert an HTML Fragment to AMP HTML
     * @return string
     * @throws \Exception
     */
    public function convertToAmpHtml()
    {
        /** @var QueryPath\DOMQuery $qp */
        if ($this->scope == Scope::BODY_SCOPE) {
            $document = $this->makeFragmentWhole($this->input_html);
        } else if ($this->scope == Scope::HTML_SCOPE) {
            $striped_html = strip_tags($this->input_html);
            if ($striped_html !== $this->input_html) { // main case
                $document = $this->input_html;
            } else {
                $document = $this->bareDocument($this->input_html);
            }
        } else {
            throw new \Exception("Invalid or currently unsupported scope $this->scope");
        }

        // Used in the StatisticsPass
        $stats_data = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'start_memory_peak' => memory_get_peak_usage()
        ];

        $this->context->setStatsData($stats_data);
        $html_processed = $this->addInternalLineNumbers($document);
        if (!empty($this->options['use_html5_parser'])) {
            $qp = QueryPath::withHTML5($html_processed, NULL, ['convert_to_encoding' => 'UTF-8']);
        } else {
            $qp = QueryPath::withHTML($html_processed, NULL, ['convert_to_encoding' => 'UTF-8']);
        }

        foreach ($this->passes as $pass_name) {
            $qp_branch = $qp->branch();

            // Each of the $qp objects are pointing to the same DOMDocument
            /** @var BasePass $pass */
            $pass = (new $pass_name($qp_branch, $this->context, $this->validation_result, $this->parsed_rules, $this->options));

            // Run the pass
            $pass->pass();
            $this->action_taken = array_merge($this->action_taken, $pass->getWarnings());
            $this->component_js = array_merge($this->component_js, $pass->getComponentJs());
        }

        $this->sortActionTakeByLineno();

        if ($this->scope == Scope::HTML_SCOPE) {
            $temp_amp_html = $qp->top()->html5();
        } else {
            $temp_amp_html = $qp->find($this->scope)->innerHTML5();
        }

        if ($this->options['add_stats_html_comment']) {
            $this->amp_html = $this->substituteStatisticsPlaceholders($temp_amp_html);
        } else {
            $this->amp_html = $temp_amp_html;
        }

        return $this->amp_html;
    }

    /**
     * @param string $input_html
     * @return string
     *
     * @TODO: Deal with potentially more edge cases and make this cross platform.
     */
    protected function addInternalLineNumbers($input_html)
    {
        $output_html = '';
        $lines = explode("\n", $input_html);
        foreach ($lines as $linenum => $input_line) {
            $output_html .= preg_replace('&(*UTF8)(<[^!/>]*?)(\s|>|$)&', '${1} data-amp-library-linenum="' . ($linenum + 1) . '"${2}', $input_line) . "\n";
        }

        return $output_html;
    }

    protected function sortActionTakeByLineno()
    {
        // Sort the warnings according to increasing line number
        // timestamp is the tie breaker
        usort($this->action_taken, function (ActionTakenLine $action_taken_1, ActionTakenLine $action_taken_2) {
            if ($action_taken_1->lineno > $action_taken_2->lineno) {
                return 1;
            } else if ($action_taken_1->lineno < $action_taken_2->lineno) {
                return -1;
            } else {
                $result = $action_taken_1->time_stamp < $action_taken_2->time_stamp ? -1 : 1;
                return $result;
            }
        });
    }

    public function getInputOutputHtmlDiff($escape_html = TRUE)
    {
        $diff = new Differ();
        $diff_html = $diff->diff($this->formatSource($this->input_html), $this->amp_html);
        if ($escape_html) {
            return htmlspecialchars($diff_html, ENT_QUOTES);
        } else {
            return $diff_html;
        }
    }

    /**
     * Quick and dirty way to format html
     * Need this if the incoming html is to be diffed to the output html in any logical way
     * @param $html
     * @return string
     * @throws \Exception
     */
    protected function formatSource($html)
    {
        if ($this->scope == Scope::BODY_SCOPE) {
            $document_html = $this->makeFragmentWhole($html);
        } else if ($this->scope == Scope::HTML_SCOPE) {
            $striped_html = strip_tags($html);
            if ($striped_html !== $html) { // main case
                $document_html = $html;
            } else {
                $document_html = $this->bareDocument($html);
            }
        } else {
            throw new \Exception("Invalid or currently unsupported scope $this->scope");
        }

        /** @var QueryPath\DOMQuery $qp */
        $qp = QueryPath::withHTML($html, NULL, ['convert_to_encoding' => 'UTF-8']);
        if ($this->scope == Scope::HTML_SCOPE) {
            return $qp->top()->html5();
        } else {
            return $qp->find($this->scope)->innerHTML5();
        }
    }

    /**
     * @return string
     */
    protected function getValidationWarnings()
    {
        /** @var RenderValidationResult $render_validation_result */
        $render_validation_result = new RenderValidationResult($this->parsed_rules->format_by_code);
        $filename = !empty($this->options['filename']) ? $this->options['filename'] : '';
        return $render_validation_result->renderValidationResult($this->validation_result, $filename);
    }

    /**
     * Use this instead of warningsHumanText() in case you want to call where html needs to be shown
     * This method makes strings html friendly with special characters escaped e.g. "<" becomes "&lt;"
     * Uses htmlspecialchars() function.
     *
     * @return string
     */
    public function warningsHumanHtml()
    {
        return htmlspecialchars($this->warningsHumanText());
    }

    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Differs from AMP::warningsHuman() in that it outputs warnings in Text and not HTML format
     *
     * Use warningsHumanHtml() if you want the string to be html friendly with special characters escaped.
     * e.g. "<" becomes "&lt;"
     *
     * @param bool $no_heading
     * @return string
     */
    public function warningsHumanText($no_heading = FALSE)
    {
        $warning_text = '';
        if (!empty($this->action_taken)) {
            if (!$no_heading) {
                $warning_text .= PHP_EOL . 'Transformations made from HTML tags to AMP custom tags';
                $warning_text .= PHP_EOL . '-------------------------------------------------------' . PHP_EOL;
            }

            /** @var ActionTakenLine $action_taken */
            foreach ($this->action_taken as $action_taken) {
                $warning_text .= PHP_EOL . "$action_taken->human_description" . PHP_EOL;
            }
        }

        if (!$no_heading) {
            $warning_text .= PHP_EOL . PHP_EOL . 'AMP-HTML Validation Issues and Fixes';
            $warning_text .= PHP_EOL . '-------------------------------------' . PHP_EOL;
        }

        $warning_text .= $this->getValidationWarnings();

        return $warning_text;
    }

    /**
     * @param string $filename
     * @param bool $full_document
     * @param bool $no_lines
     * @param bool $diff
     * @param bool $no_orig_and_warn
     * @param bool $js
     * @param bool $verbose
     * @return string
     * @throws \Exception
     */
    public function consoleOutput($filename = 'php://stdin', $full_document = false, $js = false, $no_lines = false, $diff = false, $no_orig_and_warn = false, $verbose = false)
    {
        if ($verbose) {
            error_reporting(E_ALL);
        }

        $file_html = @file_get_contents($filename);
        if ($file_html === false) {
            throw new \Exception("No such file or file not accessible: $filename Exiting...");
        }

        $options = ['filename' => $filename]; // So warnings can be printed out with filename appending to line number
        if ($full_document) {
            $options += ['scope' => Scope::HTML_SCOPE];
        }

        $this->loadHtml($file_html, $options);
        $amp_html = $this->convertToAmpHtml();

        if (!$no_lines) {
            // now this is our new output html
            $amp_html = $this->getStringWithLineNumbers($amp_html);
        }

        $output = '';
        // Show the diff if the option is set
        if (!$diff) {
            $output .= $amp_html . PHP_EOL;
        } else {
            // $escape_html is FALSE since we're outputting to the console
            $output .= $this->getInputOutputHtmlDiff($escape_html = FALSE) . PHP_EOL;
        }

        // Show the warnings by default
        if (!$no_orig_and_warn) {
            $output .= PHP_EOL . 'ORIGINAL HTML' . PHP_EOL;
            $output .= '---------------' . PHP_EOL;
            $output .= $this->getStringWithLineNumbers($this->getInputHtml()) . PHP_EOL;
            $output .= $this->warningsHumanText() . PHP_EOL;
        }

        // Show the components with js urls
        if ($js) {
            $output .= PHP_EOL . 'COMPONENT NAMES WITH JS PATH' . PHP_EOL;
            $output .= '------------------------------' . PHP_EOL;
            $output .= $this->componentList($this->getComponentJs()) . PHP_EOL;
        }

        return $output;
    }

    protected function getStringWithLineNumbers($string_input)
    {
        $lines = explode(PHP_EOL, $string_input);
        $string_output = '';
        $n = strlen((string)count($lines));
        $lineno = 0;
        foreach ($lines as $line) {
            $lineno++;
            $string_output .= sprintf("Line %{$n}d: %s" . PHP_EOL, $lineno, $line);
        }

        return $string_output;
    }

    protected function componentList($components)
    {
        $str = '';
        if (empty($components)) {
            return 'No custom amp script includes required';
        }

        foreach ($components as $name => $uri) {
            $str .= "'$name', include path '$uri'" . PHP_EOL;
        }

        return $str;
    }
}
