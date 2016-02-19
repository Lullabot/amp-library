<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Validate\RenderValidationResult;

/**
 * Class StandardScanPass
 * @package Lullabot\AMP\Pass
 *
 */
class StandardScanPass extends BasePass
{
    public function pass()
    {
        // We get back a DOMElements, this is a faster way of iterating over all tags
        // See http://technosophos.com/2009/11/26/iteration-techniques-and-performance-querypath.html
        $all_tags = $this->q->find('*')->get();

        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            $this->context->attachDomTag($tag);
            $this->parsed_rules->validateTag($this->context, $tag->nodeName, $this->encounteredAttributes($tag), $this->validation_result);
        }

        $this->parsed_rules->maybeEmitGlobalTagValidationErrors($this->context, $this->validation_result);
        // For debugging only right now
        /** @var RenderValidationResult $render_validation_result */
        $render_validation_result = new RenderValidationResult($this->parsed_rules->format_by_code);
        // For debugging/development only right now
        $filename = !empty($this->options['filename']) ? $this->options['filename'] : '';
        if (function_exists('dpm')) { // running in drupal
            dpm('AMP Library ported PHP Validator output (for debugging) ---start---');
            dpm($render_validation_result->renderValidationResult($this->validation_result));
            dpm('AMP Library ported PHP Validator output (for debugging) ---end---');
        } else {
            print('AMP Library ported PHP Validator output (for debugging) ---start---' . PHP_EOL);
            print($render_validation_result->renderValidationResult($this->validation_result, $filename));
            print('AMP Library ported PHP Validator output (for debugging) ---end---' . PHP_EOL);
        }
        // end debugging/development
        return $this->warnings;
    }
}
