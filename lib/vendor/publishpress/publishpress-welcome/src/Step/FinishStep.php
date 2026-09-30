<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

class FinishStep implements StepInterface
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
        <?php if (! empty($this->data['next_steps'])) : ?>
            <h2 class="ppw-next-steps-title"><?php echo esc_html($this->data['next_steps_title']); ?></h2>
            <ul class="ppw-next-steps">
                <?php foreach ($this->data['next_steps'] as $item) : ?>
                    <li><a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="ppw-finish">
            <?php if (! empty($this->data['primary_action'])) : ?>
                <a class="button button-primary" href="<?php echo esc_url($this->data['primary_action']['url']); ?>"><?php echo esc_html($this->data['primary_action']['label']); ?></a>
            <?php endif; ?>
            <a class="ppw-text-link" href="<?php echo esc_url($context->stepUrl(1)); ?>"><?php echo esc_html($context->config()->label('replay')); ?></a>
        </div>
        <?php if (! empty($this->data['promo'])) : ?>
            <div class="ppw-promo">
                <h2><?php echo esc_html($this->data['promo']['title']); ?></h2>
                <?php if (! empty($this->data['promo']['features'])) : ?>
                    <ul><?php foreach ($this->data['promo']['features'] as $feature) : ?><li><?php echo esc_html($feature); ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url($this->data['promo']['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($this->data['promo']['label']); ?></a>
            </div>
        <?php endif; ?>
        <?php
    }
}
