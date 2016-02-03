<?php
namespace Lullabot\AMP;

use MyCLabs\Enum\Enum;

class WarningType extends Enum
{
    const A_HREF_NO_JAVASCRIPT = 'The href attribute cannot begin with "javascript:" in an &lt;a&gt; tag.';
    const A_TARGET_ONLY_BLANK = 'The target attribute can only be "_blank" in an &lt;a&gt; tag.';
    const TAG_NOT_ALLOWED = 'This tag is not allowed in AMP HTML.';
    const ATTRIBUTE_NOT_ALLOWED = 'This attribute is not allowed in AMP HTML.';
    const COMMENT_CONDITIONAL_NOT_ALLOWED = 'Conditional comments are not allowed in AMP HTML.';
    const SCRIPT_BODY_NOT_ALLOWED = 'Script tags are not allowed &lt;body&gt; in AMP HTML.';
    const STYLE_BODY_NOT_ALLOWED = 'Style tags are not allowed &lt;body&gt; in AMP HTML.';
}
