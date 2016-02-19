<?php
namespace Lullabot\AMP;

class ActionTakenLine
{
    /** @var  integer */
    public $lineno;
    /** @var  WarningType */
    public $warning_type;
    /** @var  string */
    public $tag_attr_description;
    /** @var  string */
    public $human_description;
    /** @var  ActionTakenType */
    public $action_taken;

    /**
     * ActionTakenLine constructor.
     * @param $tag_attr_description
     * @param $warning
     * @param $lineno
     */
    public function __construct($tag_attr_description, $action, $lineno)
    {
        $this->tag_attr_description = $tag_attr_description;
        $this->action_taken = new ActionTakenType($action);
        $this->lineno = $lineno;
        $this->human_description = "Line $lineno: $tag_attr_description " .  (string)$this->action_taken;
    }
}
