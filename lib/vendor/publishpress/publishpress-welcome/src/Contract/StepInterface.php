<?php

namespace PublishPress\Welcome\Contract;

use PublishPress\Welcome\RenderContext;

interface StepInterface
{
    /**
     * Stable identifier used by filters, tests and CSS hooks.
     *
     * @return string
     */
    public function getId();

    /**
     * Render the step body. Custom implementations own escaping for custom HTML.
     *
     * @param RenderContext $context
     * @return void
     */
    public function render(RenderContext $context);
}
