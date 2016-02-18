<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Validate\RenderValidationResult;
use Lullabot\AMP\Validate\SValidationResult;
use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Validate\Context;
use Lullabot\AMP\Validate\ParsedValidatorRules;

/**
 * Class FixStandardPass
 * @package Lullabot\AMP\Pass
 *
 * @todo This pass is currently not fully implemented.
 *
 */
class FixStandardPass extends FixBasePass
{
    public function pass()
    {
        // We get back a DOMElements, this is a faster way of iterating over all tags
        // See http://technosophos.com/2009/11/26/iteration-techniques-and-performance-querypath.html
        $all_tags = $this->q->find('*')->get();

        // Initializing stuff
        $context = new Context();
        $parsed_rules = new ParsedValidatorRules($this->rules);
        $validation_result = new SValidationResult();
        $validation_result->status = ValidationResultStatus::FAIL;

        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            $context->attachDomTag($tag);
            $parsed_rules->validateTag($context, $tag->nodeName, $this->encounteredAttributes($tag), $validation_result);
        }

        $parsed_rules->maybeEmitGlobalTagValidationErrors($context, $validation_result);
        // For debugging only right now
        /** @var RenderValidationResult $render_validation_result */
        $render_validation_result = new RenderValidationResult($parsed_rules->format_by_code);
        // For debugging only right now
        if (function_exists('dpm')) {
            dpm('Ported PHP Validator results ---start---');
            dpm($render_validation_result->renderValidationResult($validation_result));
            dpm('Ported PHP Validator results ---end ---');
        } else {
            print('Ported Validator results ---start---' . PHP_EOL);
            print($render_validation_result->renderValidationResult($validation_result));
            print('Ported Validator results ---end ---' . PHP_EOL);
        }
        return $this->warnings;
    }
}
