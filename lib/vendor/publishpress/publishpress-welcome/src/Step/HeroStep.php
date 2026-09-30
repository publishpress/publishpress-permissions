<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

class HeroStep implements StepInterface
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
        $action = isset($this->data['action']) ? $this->data['action'] : [];
        ?>
        <div class="ppw-hero">
            <div class="ppw-hero-text">
                <h1><?php echo esc_html($this->data['title']); ?></h1>
                <?php if (! empty($this->data['lede'])) : ?>
                    <p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p>
                <?php endif; ?>
                <?php if (! empty($this->data['body'])) : ?>
                    <p><?php echo esc_html($this->data['body']); ?></p>
                <?php endif; ?>
                <div class="ppw-hero-actions">
                    <a class="button button-primary button-hero" href="<?php echo esc_url(isset($action['url']) ? $action['url'] : $context->stepUrl(2)); ?>">
                        <?php echo esc_html(isset($action['label']) ? $action['label'] : $context->config()->label('start')); ?>
                    </a>
                    <?php if (! empty($this->data['secondary_action'])) : ?>
                        <a class="ppw-text-link" href="<?php echo esc_url($this->data['secondary_action']['url']); ?>">
                            <?php echo esc_html($this->data['secondary_action']['label']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ppw-hero-art" aria-hidden="true">
                <?php if (! empty($this->data['art_callback']) && is_callable($this->data['art_callback'])) : ?>
                    <?php call_user_func($this->data['art_callback'], $context); ?>
                <?php elseif (! empty($this->data['asset'])) : ?>
                    <?php $context->figure($this->data['asset'], ''); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
