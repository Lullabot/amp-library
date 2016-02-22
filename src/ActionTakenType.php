<?php

namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class ActionTakenType extends Enum
{
    const TAG_REMOVED = 'tag was removed due to validation issues.';
    const ATTRIBUTE_REMOVED = 'attribute was removed due to validation issues.';
    const IMG_CONVERTED = 'tag was converted to the amp-img tag.';
    const IFRAME_CONVERTED = 'tag was converted to the amp-iframe tag.';
}
