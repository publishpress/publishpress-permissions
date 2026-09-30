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
        <h1><?php echo esc_html($this->data['title']); ?></h1>
        <?php if (! empty($this->data['lede'])) : ?><p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p><?php endif; ?>
        <div class="ppw-lesson">
            <?php $context->figure(isset($this->data['asset']) ? $this->data['asset'] : [], isset($this->data['asset_alt']) ? $this->data['asset_alt'] : '', count($callouts), $this->id); ?>
            <ol class="ppw-callouts">
                <?php foreach ($callouts as $callout) : ?><li><?php echo esc_html($callout); ?></li><?php endforeach; ?>
            </ol>
        </div>
        <?php if (! empty($this->data['action']['label'])) : ?>
            <p class="ppw-link-out">
                <a href="<?php echo esc_url($this->data['action']['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($this->data['action']['label']); ?></a>
                <?php if (! empty($this->data['action']['note'])) : ?><span><?php echo esc_html($this->data['action']['note']); ?></span><?php endif; ?>
            </p>
        <?php endif; ?>
        <?php
    }
}
