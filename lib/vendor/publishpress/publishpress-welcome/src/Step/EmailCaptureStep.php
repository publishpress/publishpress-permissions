<?php

namespace PublishPress\Welcome\Step;

use PublishPress\Welcome\Contract\StepInterface;
use PublishPress\Welcome\RenderContext;

/**
 * Optional, skippable step that asks which kind of user someone is and, with explicit consent, their email.
 *
 * The controller inserts this step automatically and handles the submission; consumers configure it through
 * the "email_capture" option rather than instantiating it.
 */
class EmailCaptureStep implements StepInterface
{
    private $id;
    private $settings;

    public function __construct(array $settings)
    {
        $this->id = 'email-capture';
        $this->settings = $settings;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * The form has its own submit and decline buttons.
     *
     * @return bool
     */
    public function hasOwnNavigation()
    {
        return true;
    }

    public function render(RenderContext $context)
    {
        $copy = $this->settings['copy'];
        $state = $context->emailCapture();
        $recipient = isset($this->settings['recipient_name']) ? $this->settings['recipient_name'] : '';
        ?>
        <div class="ppw-heading">
            <h1><?php echo esc_html($copy['title']); ?></h1>
            <p class="ppw-lede"><?php echo esc_html($copy['lede']); ?></p>
        </div>

        <form class="ppw-capture" method="post" action="<?php echo esc_url($state['action_url']); ?>">
            <?php wp_nonce_field($state['nonce_action']); ?>

            <?php if ($state['error']) : ?>
                <p class="ppw-capture-error" role="alert"><?php echo esc_html($state['error']); ?></p>
            <?php endif; ?>

            <?php if (! empty($this->settings['personas'])) : ?>
                <fieldset class="ppw-field">
                    <legend><?php echo esc_html($copy['persona_label']); ?></legend>
                    <div class="ppw-personas">
                        <?php foreach ($this->settings['personas'] as $key => $label) : ?>
                            <label class="ppw-persona">
                                <input type="radio" name="ppw_persona" value="<?php echo esc_attr($key); ?>" <?php checked($state['values']['persona'], (string) $key); ?> />
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <div class="ppw-field">
                <label for="ppw-email"><?php echo esc_html($copy['email_label']); ?></label>
                <input class="ppw-input" type="email" id="ppw-email" name="ppw_email" autocomplete="email" required value="<?php echo esc_attr($state['values']['email']); ?>" />
            </div>

            <label class="ppw-consent">
                <input type="checkbox" name="ppw_consent" value="1" <?php checked($state['values']['consent']); ?> />
                <span><?php echo esc_html($copy['consent_label']); ?></span>
            </label>

            <p class="ppw-disclosure">
                <?php echo esc_html(sprintf($copy['disclosure'], $recipient)); ?>
                <?php if (! empty($this->settings['privacy_url'])) : ?>
                    <a href="<?php echo esc_url($this->settings['privacy_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($copy['privacy_label']); ?><span class="ppw-sr-only"> <?php echo esc_html($context->config()->label('new_tab')); ?></span></a>
                <?php endif; ?>
            </p>

            <p class="ppw-disclosure"><?php echo esc_html($copy['confirmation_note']); ?></p>

            <div class="ppw-capture-actions">
                <button type="submit" class="ppw-btn ppw-btn-primary ppw-btn-lg" name="ppw_email_action" value="subscribe">
                    <?php echo esc_html($copy['submit']); ?> <span class="ppw-btn-arrow" aria-hidden="true">&rarr;</span>
                </button>
                <button type="submit" class="ppw-link-button" name="ppw_email_action" value="decline" formnovalidate>
                    <?php echo esc_html($copy['decline']); ?>
                </button>
            </div>
        </form>
        <?php
    }
}
