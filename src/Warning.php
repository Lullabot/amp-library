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

    /**
     * Warning constructor.
     * @param $tag_attr_description
     * @param $warning
     * @param $warning_code
     * @param $action
     * @param $lineno
     */
    public function __construct($tag_attr_description, $warning, $warning_code, $action, $lineno)
    {
        $this->warning_type = new WarningType($warning);
        $this->tag_attr_description = $tag_attr_description;
        $this->action_taken = new ActionTaken($action);
        $this->lineno = $lineno;
        $this->human_description = "<strong>Line $lineno: $tag_attr_description</strong> " . (string)$this->warning_type . " [$warning_code] " . (string)$this->action_taken;
    }
}
