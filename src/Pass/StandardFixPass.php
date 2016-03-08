<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
    protected $remove_properties_for_codes = [
        ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE,
        ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE
    ];

    protected $remove_attributes_for_codes = [
        ValidationErrorCode::INVALID_URL_PROTOCOL,
        ValidationErrorCode::INVALID_URL,
        ValidationErrorCode::INVALID_ATTR_VALUE,
        ValidationErrorCode::DISALLOWED_ATTR,
        ValidationErrorCode::MISSING_URL,
        // @todo ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS?
    ];
    protected $remove_tags_for_codes = [
        ValidationErrorCode::WRONG_PARENT_TAG,
        ValidationErrorCode::DISALLOWED_TAG,
        ValidationErrorCode::DUPLICATE_UNIQUE_TAG
    ];

    public function pass()
    {
        /** @var \DOMElement $last_rem_dom_tag_for_attr */
        $last_rem_dom_tag_for_attr = null;
        /** @var \DOMElement $last_rem_dom_tag */
        $last_rem_dom_tag = null;
        $last_rem_dom_attr_name = '';

        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if (empty($error->dom_tag)) {
                continue;
            }

            $tag_name = $error->dom_tag->tagName;

            // Property value pairs
            if (in_array($error->code, $this->remove_properties_for_codes)
                && !empty($error->attr_name)
                && !empty($error->segment)
                && $error->dom_tag->hasAttribute($error->attr_name)
            ) {
                // No point removing property if we just removed the attribute
                if (!empty($last_rem_dom_attr_name) && $last_rem_dom_attr_name === $error->attr_name) {
                    continue;
                }

                // First try replacing with comma appended
                // Note the str_replace is fine with utf8, we don't need an mb_ equivalent here
                $new_attr_value = str_replace("$error->segment,", '', $error->dom_tag->getAttribute($error->attr_name));
                $new_attr_value = str_replace($error->segment, '', $new_attr_value);

                if (empty(trim($new_attr_value))) {  // There is nothing here now so we should just remove the attribute
                    $last_rem_dom_attr_name = $error->attr_name;
                    $last_rem_dom_tag_for_attr = $error->dom_tag;
                    $error->dom_tag->removeAttribute($error->attr_name);
                    $error->addActionTaken(new ActionTakenLine("$tag_name.$error->attr_name=\"$error->segment\"", ActionTakenType::PROPERTY_REMOVED_ATTRIBUTE_REMOVED, $error->line));
                } else {
                    $error->dom_tag->setAttribute($error->attr_name, $new_attr_value);
                    $error->addActionTaken(new ActionTakenLine("$tag_name.$error->attr_name=\"$error->segment\"", ActionTakenType::PROPERTY_REMOVED, $error->line));
                }
            }

            // Attributes
            if (in_array($error->code, $this->remove_attributes_for_codes)
                && !empty($error->attr_name)
                && $error->dom_tag->hasAttribute($error->attr_name)
            ) {
                // No point removing attribute if we already removed the tag!
                if (!empty($last_rem_dom_tag) && $last_rem_dom_tag->isSameNode($error->dom_tag)) {
                    continue;
                }

                // Don't remove the same attribute again and again
                if (!empty($last_rem_dom_tag_for_attr) &&
                    $error->dom_tag->isSameNode($last_rem_dom_tag_for_attr) &&
                    $last_rem_dom_attr_name === $error->attr_name
                ) {
                    continue;
                }

                // Remove the offending attribute
                $last_rem_dom_attr_name = $error->attr_name;
                $last_rem_dom_tag_for_attr = $error->dom_tag;
                $error->dom_tag->removeAttribute($error->attr_name);
                $error->addActionTaken(new ActionTakenLine("$tag_name.$error->attr_name", ActionTakenType::ATTRIBUTE_REMOVED, $error->line));
            }

            // Tags
            if (in_array($error->code, $this->remove_tags_for_codes) && !empty($error->dom_tag)) {
                // Don't remove the same tag again and again
                if (!empty($last_rem_dom_tag) && $error->dom_tag->isSameNode($last_rem_dom_tag)) {
                    continue;
                }

                $last_rem_dom_tag = $error->dom_tag;
                // Remove the offending tag
                $error->dom_tag->parentNode->removeChild($error->dom_tag);
                $error->addActionTaken(new ActionTakenLine($tag_name, ActionTakenType::TAG_REMOVED, $error->line));
            }
        }

        return [];
    }
}
