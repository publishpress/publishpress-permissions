<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

/**
 * A grid of short feature cards, each with a Dashicon, a title and a sentence of copy.
 *
 * Supported data keys: title, lede, features (each: icon, title, body) and columns (2 or 3).
 */
class FeaturesStep implements StepInterface
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
        $columns = isset($this->data['columns']) && 2 === (int) $this->data['columns'] ? 2 : 3;
        ?>
        <div class="ppw-heading">
            <h1><?php echo esc_html($this->data['title']); ?></h1>
            <?php if (! empty($this->data['lede'])) : ?><p class="ppw-lede"><?php echo esc_html($this->data['lede']); ?></p><?php endif; ?>
        </div>
        <ul class="ppw-features ppw-cols-<?php echo esc_attr($columns); ?>">
            <?php foreach ((array) $this->data['features'] as $feature) : ?>
                <li class="ppw-feature">
                    <span class="ppw-feature-icon dashicons <?php echo esc_attr(sanitize_html_class(isset($feature['icon']) ? $feature['icon'] : 'dashicons-yes')); ?>" aria-hidden="true"></span>
                    <span class="ppw-feature-copy">
                        <strong><?php echo esc_html($feature['title']); ?></strong>
                        <?php if (! empty($feature['body'])) : ?><span><?php echo esc_html($feature['body']); ?></span><?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}
