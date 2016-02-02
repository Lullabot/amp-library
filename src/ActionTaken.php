<?php

namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class ActionTaken extends Enum
{
    const TAG_REMOVED = 'The tag was removed.';
    const ATTRIBUTE_REMOVED = 'The attribute was removed.';
    const IMG_CONVERTED = 'The &lt;img&gt; tag was converted to the &lt;amp-img&gt; tag.';
    const IFRAME_CONVERTED = 'The &lt;iframe&gt; was converted to the &lt;amp-iframe&gt; tag.';
}
