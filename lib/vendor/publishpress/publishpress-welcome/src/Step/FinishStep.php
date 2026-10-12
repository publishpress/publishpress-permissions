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
        <div class="ppw-finish-head">
            <span class="ppw-success" aria-hidden="true"><span class="ppw-success-mark"></span></span>
            <h1><?php echo esc_html($this->data['title']); ?></h1>
            <?php if (! empty($this->data['lede'])) : ?><p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p><?php endif; ?>
        </div>
        <?php if (! empty($this->data['next_steps'])) : ?>
            <?php if (! empty($this->data['next_steps_title'])) : ?><h2 class="ppw-next-steps-title"><?php echo esc_html($this->data['next_steps_title']); ?></h2><?php endif; ?>
            <ol class="ppw-next-steps">
                <?php foreach ($this->data['next_steps'] as $item) : ?>
                    <li><a href="<?php echo esc_url($item['url']); ?>"><span class="ppw-next-label"><?php echo esc_html($item['label']); ?></span><span class="ppw-next-arrow" aria-hidden="true">&rarr;</span></a></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <div class="ppw-finish">
            <?php if (! empty($this->data['primary_action'])) : ?>
                <a class="ppw-btn ppw-btn-primary ppw-btn-lg" href="<?php echo esc_url($this->data['primary_action']['url']); ?>"><?php echo esc_html($this->data['primary_action']['label']); ?></a>
            <?php endif; ?>
            <a class="ppw-text-link" href="<?php echo esc_url($context->stepUrl(1)); ?>"><?php echo esc_html($context->config()->label('replay')); ?></a>
        </div>
        <?php if (! empty($this->data['promo'])) : ?>
            <div class="ppw-promo">
                <div class="ppw-promo-copy">
                    <h2><?php echo esc_html($this->data['promo']['title']); ?></h2>
                    <?php if (! empty($this->data['promo']['features'])) : ?>
                        <ul><?php foreach ($this->data['promo']['features'] as $feature) : ?><li><?php echo esc_html($feature); ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
                <a class="ppw-btn ppw-btn-light" href="<?php echo esc_url($this->data['promo']['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($this->data['promo']['label']); ?><span class="ppw-sr-only"> <?php echo esc_html($context->config()->label('new_tab')); ?></span></a>
            </div>
        <?php endif; ?>
        <?php
    }
}
