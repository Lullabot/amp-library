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
namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\ChildTagSpec;
use Lullabot\AMP\Spec\TagSpec;
use Lullabot\AMP\Spec\ValidationErrorCode;

/**
 * Class ChildTagMatcher
 * @package Lullabot\AMP\Validate
 *
 * This class is a straight PHP port of the ChildTagMatcher class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/main/validator/validator.js )
 *
 */
class ChildTagMatcher
{
    /** @var TagSpec */
    protected $parent_spec = null;

    public function __construct(TagSpec $parent_spec)
    {
        $this->parent_spec = $parent_spec;
    }

    protected function isEnabled()
    {
        if (empty($this->parent_spec)) {
            return false;
        }
        /** @var ChildTagSpec|null $child_tag_spec */
        $child_tag_spec = $this->parent_spec->child_tags;
        if (empty($child_tag_spec)) {
            return false;
        }

        return true;
    }

    /**
     * @param Context $context
     * @param SValidationResult $validation_result
     */
    public function matchChildTagName(Context $context, SValidationResult $validation_result)
    {
        if (!$this->isEnabled()) {
            return;
        }

        /** @var ChildTagSpec|null $child_tag_spec */
        $child_tag_spec = $this->parent_spec->child_tags;

        /** @var string[] $child_tag_names */
        $child_tag_names = $context->getChildTagNames();
        $num_child_tags = count($child_tag_names);

        /** @var string[]|null $first_name_oneof */
        $first_name_oneof = $child_tag_spec->first_child_tag_name_oneof;
        if (!empty($first_name_oneof) && !in_array($child_tag_names[0], $first_name_oneof)) {
            $allowed_names = '[' . join(',', $first_name_oneof) . ']';
            $context->addError(ValidationErrorCode::DISALLOWED_FIRST_CHILD_TAG_NAME,
                [$child_tag_names[0], $this->parent_spec->tag_name, $allowed_names], $this->parent_spec->spec_url, $validation_result);
        }

        /** @var string[]|null $child_tag_name_oneof */
        $child_tag_name_oneof = $child_tag_spec->child_tag_name_oneof;
        if (!empty($child_tag_name_oneof)) {
            foreach ($child_tag_names as $child_tag_name) {
                if (!in_array($child_tag_name, $child_tag_name_oneof)) {
                    $allowed_names = '[' . join(',', $child_tag_name_oneof) . ']';
                    $context->addError(ValidationErrorCode::DISALLOWED_CHILD_TAG_NAME,
                        [$child_tag_name, $this->parent_spec->tag_name, $allowed_names], $this->parent_spec->spec_url, $validation_result);
                }
            }
        }

        /** @var number|null $mandatory_num_child_tags */
        $mandatory_num_child_tags = $child_tag_spec->mandatory_num_child_tags;
        if (is_numeric($mandatory_num_child_tags) && $num_child_tags !== $mandatory_num_child_tags) {
            $context->addError(ValidationErrorCode::INCORRECT_NUM_CHILD_TAGS,
                [$this->parent_spec->tag_name, $mandatory_num_child_tags, $num_child_tags], $this->parent_spec->spec_url, $validation_result);
        }
    }
}
