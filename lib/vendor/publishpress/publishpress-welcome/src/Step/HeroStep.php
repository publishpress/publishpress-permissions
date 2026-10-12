<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

/**
 * Opening step: headline, lede, a primary call to action and optional artwork.
 *
 * Supported data keys: title, lede, body, eyebrow, highlights (string[]), action (label, url),
 * secondary_action (label, url), asset (path, url) and art_callback (callable).
 */
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

    /**
     * The hero renders its own primary call to action, so the shared footer omits "Next".
     *
     * @return bool
     */
    public function hasOwnNavigation()
    {
        return true;
    }

    public function render(RenderContext $context)
    {
        $action = isset($this->data['action']) ? $this->data['action'] : [];
        $highlights = isset($this->data['highlights']) ? (array) $this->data['highlights'] : [];
        $hasArt = ! empty($this->data['art_callback']) || ! empty($this->data['asset']);
        ?>
        <div class="ppw-hero<?php echo $hasArt ? ' has-art' : ''; ?>">
            <div class="ppw-hero-text">
                <?php if (! empty($this->data['eyebrow'])) : ?>
                    <span class="ppw-eyebrow"><?php echo esc_html($this->data['eyebrow']); ?></span>
                <?php endif; ?>
                <h1><?php echo esc_html($this->data['title']); ?></h1>
                <?php if (! empty($this->data['lede'])) : ?>
                    <p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p>
                <?php endif; ?>
                <?php if (! empty($this->data['body'])) : ?>
                    <p class="ppw-hero-body"><?php echo esc_html($this->data['body']); ?></p>
                <?php endif; ?>
                <?php if ($highlights) : ?>
                    <ul class="ppw-highlights">
                        <?php foreach ($highlights as $highlight) : ?><li><?php echo esc_html($highlight); ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="ppw-hero-actions">
                    <a class="ppw-btn ppw-btn-primary ppw-btn-lg" href="<?php echo esc_url(isset($action['url']) ? $action['url'] : $context->stepUrl(2)); ?>">
                        <?php echo esc_html(isset($action['label']) ? $action['label'] : $context->config()->label('start')); ?>
                        <span class="ppw-btn-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                    <?php if (! empty($this->data['secondary_action'])) : ?>
                        <a class="ppw-text-link" href="<?php echo esc_url($this->data['secondary_action']['url']); ?>">
                            <?php echo esc_html($this->data['secondary_action']['label']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($hasArt) : ?>
                <div class="ppw-hero-art" aria-hidden="true">
                    <?php if (! empty($this->data['art_callback']) && is_callable($this->data['art_callback'])) : ?>
                        <?php call_user_func($this->data['art_callback'], $context); ?>
                    <?php elseif (! empty($this->data['asset'])) : ?>
                        <?php $context->figure($this->data['asset'], ''); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
