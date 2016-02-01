<?php
namespace Lullabot\AMP;

class Warning {
    /** @var  integer */
    public $lineno;
    /** @var  WarningType */
    public $warning_type;
    /** @var  string */
    public $human_description;
    /** @var  ActionTaken */
    public $action_taken;

    public function __construct($warning, $action, $lineno) {
        $this->warning_type = new WarningType($warning);
        $this->action = new ActionTaken($action);
        $this->lineno = $lineno;
        $this->human_description = (string) $this->warning_type . (string) $this->action_taken . ' at line: ' . $lineno;
    }
}
