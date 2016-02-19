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
        return $this->warnings;
    }
}
