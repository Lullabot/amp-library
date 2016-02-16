<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Validate\SValidationResult;
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
        $context = new Context();
        $parsed_rules = new ParsedValidatorRules($this->rules);
        $validation_result = new SValidationResult();
        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            $context->substituteTag($tag);
            $parsed_rules->validateTag($context, $tag->nodeName, $this->encounteredAttributes($tag), $validation_result);
        }

        $parsed_rules->maybeEmitGlobalTagValidationErrors($context, $validation_result);
        // For debugging only right now
        if (function_exists('dpm')) {
            dpm($validation_result);
        }
        else {
            print_r($validation_result);
        }
        return $this->warnings;
    }
}
