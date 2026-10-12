<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

class LessonStep implements StepInterface
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
        $callouts = isset($this->data['callouts']) ? (array) $this->data['callouts'] : [];
        ?>
        <div class="ppw-lesson">
            <div class="ppw-lesson-copy">
                <h1><?php echo esc_html($this->data['title']); ?></h1>
                <?php if (! empty($this->data['lede'])) : ?><p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p><?php endif; ?>
                <ol class="ppw-callouts">
                    <?php foreach ($callouts as $callout) : ?><li><?php echo esc_html($callout); ?></li><?php endforeach; ?>
                </ol>
                <?php if (! empty($this->data['action']['label'])) : ?>
                    <p class="ppw-link-out">
                        <a class="ppw-btn ppw-btn-outline" href="<?php echo esc_url($this->data['action']['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($this->data['action']['label']); ?> <span aria-hidden="true">&nearr;</span><span class="ppw-sr-only"> <?php echo esc_html($context->config()->label('new_tab')); ?></span></a>
                        <?php if (! empty($this->data['action']['note'])) : ?><span class="ppw-link-note"><?php echo esc_html($this->data['action']['note']); ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="ppw-lesson-art">
                <?php $context->figure(isset($this->data['asset']) ? $this->data['asset'] : [], isset($this->data['asset_alt']) ? $this->data['asset_alt'] : '', count($callouts), $this->id); ?>
            </div>
        </div>
        <?php
    }
}
