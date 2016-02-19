<?php
namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Validate\SValidationError;
use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class StandardFixPass
 * @package Lullabot\AMP\Pass
 *
 * @todo make more sophisticated
 *
 */
class StandardFixPass extends BasePass
{
    protected $remove_attributes_for_codes = [
        ValidationErrorCode::INVALID_URL_PROTOCOL,
        ValidationErrorCode::INVALID_URL,
        ValidationErrorCode::INVALID_ATTR_VALUE,
        ValidationErrorCode::DISALLOWED_ATTR,
        ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE,
        ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
        ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE,
        ValidationErrorCode::MISSING_URL,
        // @todo ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS?
    ];
    protected $remove_tags_for_codes = [
        ValidationErrorCode::WRONG_PARENT_TAG,
        ValidationErrorCode::DISALLOWED_TAG,
        // @todo ValidationErrorCode::DUPLICATE_UNIQUE_TAG?
    ];

    public function pass()
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if (empty($error->dom_tag)) {
                continue;
            }

            $tag_name = $error->dom_tag->tagName;
            if (in_array($error->code, $this->remove_attributes_for_codes) && !empty($error->attr_name)) {
                // Is there a more generic way to do this?
                if ($error->attr_name == 'src' && $tag_name = 'img') {
                    continue;
                }
                $error->dom_tag->removeAttribute($error->attr_name);
                $this->addActionTaken(new ActionTakenLine("$tag_name.$error->attr_name", ActionTakenType::ATTRIBUTE_REMOVED, $error->line));
            }

            if (in_array($error->code, $this->remove_tags_for_codes) && !empty($error->dom_tag)) {
                // Remove the offending tag
                $error->dom_tag->parentNode->removeChild($error->dom_tag);
                $this->addActionTaken(new ActionTakenLine($tag_name, ActionTakenType::TAG_REMOVED, $error->line));
            }
        }

        return $this->warnings;
    }
}
