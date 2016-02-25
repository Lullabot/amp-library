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
    /** @var  string */
    public $context_string = '';
    /** @var  ActionTakenType */
    public $action_taken;
    /** @var  number */
    public $time_stamp;

    /**
     * ActionTakenLine constructor.
     * @param string $tag_attr_description
     * @param string $action
     * @param string|number $lineno
     * @param string $context_string
     */
    public function __construct($tag_attr_description, $action, $lineno, $context_string = '')
    {
        $this->tag_attr_description = $tag_attr_description;
        $this->action_taken = new ActionTakenType($action);
        $this->lineno = $lineno;
        $this->time_stamp = microtime(true);
        $this->context_string = $context_string;
        $this->human_description = "Line $lineno: $tag_attr_description " . (string)$this->action_taken;
        if (!empty($context_string)) {
            $this->human_description .= " [context: $context_string] ";
        }
    }
}
