<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

/**
 * Class FixStandardPass
 * @package Lullabot\AMP\Pass
 *
 * @todo This pass is currently not implemented.
 *
 */
class FixStandardPass extends FixBasePass
{
    public function pass()
    {
        // We get back a DOMElements, this is a faster way of iterating over all tags
        // See http://technosophos.com/2009/11/26/iteration-techniques-and-performance-querypath.html
        $all_tags = $this->q->find('*')->get();

        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            // @todo
        }

        return $this->warnings;
    }
}
