<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

/** Consumer-owned markup, including forms whose handlers stay in the consuming plugin. */
class CallbackStep implements StepInterface
{
    private $id;
    private $renderer;
    private $ownNavigation;

    public function __construct($id, callable $renderer, $ownNavigation = false)
    {
        $this->id = sanitize_key($id);
        $this->renderer = $renderer;
        $this->ownNavigation = (bool) $ownNavigation;
    }

    public function getId()
    {
        return $this->id;
    }

    public function hasOwnNavigation()
    {
        return $this->ownNavigation;
    }

    public function render(RenderContext $context)
    {
        call_user_func($this->renderer, $context);
    }
}
