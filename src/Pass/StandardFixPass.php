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
use Lullabot\AMP\Utility\ActionTakenLine;
use Lullabot\AMP\Utility\ActionTakenType;

/**
 * Class StandardFixPass
 * @package Lullabot\AMP\Pass
 *
 * Basic AMP validation fixes
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
        ValidationErrorCode::UNESCAPED_TEMPLATE_IN_ATTR_VALUE,
        ValidationErrorCode::TEMPLATE_PARTIAL_IN_ATTR_VALUE,
        ValidationErrorCode::ATTR_DISALLOWED_BY_SPECIFIED_LAYOUT,
        ValidationErrorCode::ATTR_DISALLOWED_BY_IMPLIED_LAYOUT,
        ValidationErrorCode::SPECIFIED_LAYOUT_INVALID
    ];

    protected $remove_tags_for_codes = [
        ValidationErrorCode::WRONG_PARENT_TAG,
        ValidationErrorCode::DISALLOWED_TAG,
        ValidationErrorCode::DISALLOWED_TAG_ANCESTOR,
        ValidationErrorCode::DUPLICATE_UNIQUE_TAG,
        ValidationErrorCode::GENERAL_DISALLOWED_TAG
    ];

    public function pass()
    {
        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            // If the error was resolved, continue
            if ($error->resolved) {
                continue;
            }

            // Special Case for TAG_REQUIRED_BY_MISSING
            // Add custom component script tag
            if ($error->code == ValidationErrorCode::TAG_REQUIRED_BY_MISSING) {
                if (!empty($error->params[1]) && $this->addComponentJsToHead($error->params[1])) {
                    $error->addActionTaken(new ActionTakenLine($error->params[1], ActionTakenType::COMPONENT_SCRIPT_TAG_ADDED, $error->line));
                    $error->resolved = true;
                }
            }

            // Does the tag exist?
            if (empty($error->dom_tag) || empty($error->dom_tag->parentNode)) {
                continue;
            }

            $tag_name = mb_strtolower($error->dom_tag->tagName, 'UTF-8');

            // Property value pairs
            if (in_array($error->code, $this->remove_properties_for_codes)
                && !empty($error->attr_name)
                && !empty($error->segment)
                && $error->dom_tag->hasAttribute($error->attr_name)
            ) {
                // First try replacing with comma appended
                // Note the str_replace is fine with utf8, we don't need an mb_ equivalent here
                $new_attr_value = str_replace("$error->segment,", '', $error->dom_tag->getAttribute($error->attr_name));
                $new_attr_value = str_replace($error->segment, '', $new_attr_value);

                $new_attr_value_trimmed = trim($new_attr_value);
                if (empty($new_attr_value_trimmed)) {  // There is nothing here now so we should just remove the attribute
                    $error->dom_tag->removeAttribute($error->attr_name);
                    $error->addActionTaken(new ActionTakenLine("In $tag_name.$error->attr_name the \"$error->segment\"", ActionTakenType::PROPERTY_REMOVED_ATTRIBUTE_REMOVED, $error->line));
                } else {
                    $error->dom_tag->setAttribute($error->attr_name, $new_attr_value);
                    $error->addActionTaken(new ActionTakenLine("In $tag_name.$error->attr_name the \"$error->segment\"", ActionTakenType::PROPERTY_REMOVED, $error->line));
                }
            }

            // Attributes
            if (in_array($error->code, $this->remove_attributes_for_codes)
                && !empty($error->attr_name)
                && $error->dom_tag->hasAttribute($error->attr_name)
            ) {
                $error->dom_tag->removeAttribute($error->attr_name);
                $error->addActionTaken(new ActionTakenLine("$tag_name.$error->attr_name", ActionTakenType::ATTRIBUTE_REMOVED, $error->line));
            }

            // Tags
            if (in_array($error->code, $this->remove_tags_for_codes) && !empty($error->dom_tag)) {
                // Remove the offending tag
                $error->dom_tag->parentNode->removeChild($error->dom_tag);
                $error->addActionTaken(new ActionTakenLine($tag_name, ActionTakenType::TAG_REMOVED, $error->line));
            }

        }

        return [];
    }
}
