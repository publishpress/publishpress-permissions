<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

/**
 * A guide step whose markup is produced by the consuming plugin, for content the shared steps do not cover.
 * The renderer owns escaping.
 */
class WelcomeCallbackStep implements StepInterface
{
    private $id;
    private $renderer;
    private $ownNavigation;

    /**
     * @param string   $id
     * @param callable $renderer      Receives the RenderContext.
     * @param bool     $ownNavigation True when the step renders its own forward action, so the shared "Next" is hidden.
     */
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
