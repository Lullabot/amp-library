<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Warning;
use Lullabot\AMP\WarningType;
use Lullabot\AMP\ActionTaken;

/**
 * Class FixTagsAndAttributes
 * @package Lullabot\AMP\Pass
 *
 * - Remove all non-whitelisted tags
 * - Remove all attributes that start with "on" e.g. "onmouseover". However, "on" by itself as an attribute name is OK
 */
class FixTagsAndAttributesPass extends FixHTMLPass
{
    // These are the whitelisted tags
    // All other tags will be stripped out
    // List from https://github.com/ampproject/amphtml/blob/master/spec/amp-tag-addendum.md
    private static $tag_whitelist = [
        // Root Element
        'html',
        // Document Metadata
        'head',
        'title',
        'link',
        'meta',
        'style',
        // Sections
        'body',
        'article',
        'section',
        'nav',
        'aside',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'footer',
        'address',
        // Grouping
        'p',
        'hr',
        'pre',
        'blockquote',
        'ol',
        'ul',
        'li',
        'dl',
        'dt',
        'dd',
        'figure',
        'figcaption',
        'div',
        'main',
        // Text level
        'a',
        'em',
        'strong',
        'small',
        's',
        'cite',
        'q',
        'dfn',
        'abbr',
        'data',
        'time',
        'code',
        'var',
        'samp',
        'kbd ',
        'sub',
        'sup',
        'i',
        'b',
        'u',
        'mark',
        'ruby',
        'rb',
        'rt',
        'rtc',
        'rp',
        'bdi',
        'bdo',
        'span',
        'br',
        'wbr',
        // Edits
        'ins',
        'del',
        // 4.7.8
        'source',
        // SVG
        'svg',
        'g',
        'path',
        'glyph',
        'glyphref',
        'marker',
        'view',
        'circle',
        'line',
        'polygon',
        'polyline',
        'rect',
        'text',
        'textpath',
        'tref',
        'tspan',
        'clippath',
        'filter',
        'lineargradient',
        'radialgradient',
        'mask',
        'pattern',
        'vkern',
        'hkern',
        'defs',
        'use',
        'symbol',
        'desc',
        'title',
        // Tabular Data
        'table',
        'caption',
        'colgroup',
        'col',
        'tbody',
        'thead',
        'tfoot',
        'tr',
        'td',
        'th',
        // Forms
        'button',
        // Scripting
        'script',
        'noscript',
        // Non-confirming features
        // These may be removed in future versions of AMP
        'acronym',
        'center',
        'dir',
        'hgroup',
        'listing',
        'multicol',
        'nextid',
        'nobr',
        'spacer',
        'strike',
        'tt',
        'xmp',
        // Amp Specific Tags
        'amp-img',
        'amp-video',
        'amp-ad',
        'amp-fit-text',
        'amp-font',
        'amp-carousel',
        'amp-anim',
        'amp-youtube',
        'amp-twitter',
        'amp-vine',
        'amp-instagram',
        'amp-iframe',
        'amp-pixel',
        'amp-audio',
        'amp-lightbox',
        'amp-image-lightbox'];

    public function pass()
    {
        // We get back a DOMElements, this is a faster way of iterating over all tags
        // See http://technosophos.com/2009/11/26/iteration-techniques-and-performance-querypath.html
        $all_tags = $this->q->find('*')->get();

        /** @var \DOMElement $tag */
        foreach ($all_tags as $tag) {
            if (!in_array($tag->nodeName, self::$tag_whitelist)) {
                $this->addWarning(new Warning($tag->nodeName, WarningType::TAG_NOT_ALLOWED, ActionTaken::TAG_REMOVED, $tag->getLineNo()));
                $tag->parentNode->removeChild($tag);
                continue;
            }

            // Iterate over attributes just like in QueryPath\DOMQuery::attr() function
            /**
             * @var  string $name
             * @var \DOMNode $attrNode
             */
            foreach ($tag->attributes as $name => $attrNode) {
                // Something like "onmouseover" is not allowed but just "on" by itself, is.
                if (preg_match('/^on./', $name)) {
                    $this->addWarning(new Warning($name, WarningType::ATTRIBUTE_NOT_ALLOWED, ActionTaken::ATTRIBUTE_REMOVED, $tag->getLineNo()));
                    $tag->removeAttribute($name);
                }
            }
        }

        return $this->warnings;
    }
}