<?php
namespace Lullabot\AMP;

class Warning
{
    /** @var  integer */
    public $lineno;
    /** @var  WarningType */
    public $warning_type;
    /** @var  string */
    public $tag_attr_description;
    /** @var  string */
    public $human_description;
    /** @var  ActionTaken */
    public $action_taken;

    public function __construct($tag_attr_description, $warning, $action, $lineno)
    {
        $this->warning_type = new WarningType($warning);
        $this->tag_attr_description = $tag_attr_description;
        $this->action_taken = new ActionTaken($action);
        $this->lineno = $lineno;
        $this->human_description = "<strong>Line $lineno: $tag_attr_description</strong> " . (string)$this->warning_type . ' ' . (string)$this->action_taken;
    }
}
