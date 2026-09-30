<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

class ConceptsStep implements StepInterface
{
    private $id;
    private $data;

    public function __construct($id, array $data)
    {
        $this->id = sanitize_key($id);
        $this->data = $data;
    }

    public function getId()
    {
        return $this->id;
    }

    public function render(RenderContext $context)
    {
        ?>
        <h1><?php echo esc_html($this->data['title']); ?></h1>
        <?php if (! empty($this->data['lede'])) : ?><p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p><?php endif; ?>
        <div class="ppw-concepts">
            <?php foreach ((array) $this->data['concepts'] as $concept) : ?>
                <div class="ppw-concept">
                    <?php $context->figure(isset($concept['asset']) ? $concept['asset'] : [], $concept['name'], 0, isset($concept['slug']) ? $concept['slug'] : ''); ?>
                    <h2><?php echo esc_html($concept['name']); ?></h2>
                    <p><?php echo esc_html($concept['body']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (! empty($this->data['aside'])) : ?><p class="ppw-aside"><?php echo esc_html($this->data['aside']); ?></p><?php endif; ?>
        <?php
    }
}
