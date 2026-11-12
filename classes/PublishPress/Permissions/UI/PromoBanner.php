<?php

/**
 * @package     PublishPress\Permissions
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.1.0
 */

namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class PromoBanner
{
  /**
   * @var array $features List of features for the plugin.
   */
  private $features = [];

  /**
   * @var string $pluginDocsUrl URL to the plugin documentation.
   */
  private $pluginDocsUrl;

  /**
   * @var string $pluginName Name of the plugin.
   */
  private $pluginName;

  /**
   * @var string $pluginSupportUrl URL to the plugin support page.
   */
  private $pluginSupportUrl;

  /**
   * @var string $pluginUrl URL to the plugin homepage.
   */
  private $pluginUrl;

  /**
   * @var string $title Title of the promo banner.
   */
  private $title;

  /**
   * @var string $subtitle Subtitle of the promo banner.
   */
  private $subtitle;

  /**
   * @var string $supportTitle Title for the support section in the promo banner.
   */
  private $supportTitle;

  /**
   * @var string $supportSubtitle Subtitle for the support section in the promo banner.
   */
  private $supportSubtitle;

  public function __construct($args = [])
  {
    $pluginData = get_file_data(PRESSPERMIT_FILE, ['PluginURI' => 'Plugin URI', 'Name' => 'Plugin Name']);
    $this->pluginUrl = $pluginData['PluginURI'];
    $this->pluginName = $pluginData['Name'];
    $this->title = esc_html__("Upgrade to {$this->pluginName} Pro", 'press-permit-core');
    $this->subtitle = esc_html__("Enhance the power of {$this->pluginName} with the Pro version:", 'press-permit-core');
    $this->supportTitle = esc_html__("Need {$this->pluginName} Support?", 'press-permit-core');
    $this->supportSubtitle = esc_html__('If you need help or have a new feature request, let us know.', 'press-permit-core');

    foreach ($args as $key => $val) {
      if (property_exists($this, $key)) {
        $this->{$key} = $val;
      }
    }
  }

  public function setTitle($title)
  {
    $this->title = $title;
  }

  public function setSubtitle($subtitle)
  {
    $this->subtitle = $subtitle;
  }

  public function setPluginName($name)
  {
    $this->pluginName = $name;
  }

  public function setFeatures(array $features)
  {
    $this->features = $features;
  }

  public function setPluginDocsUrl($url)
  {
    $this->pluginDocsUrl = $url;
  }

  public function setPluginSupportUrl($url)
  {
    $this->pluginSupportUrl = $url;
  }

  public function setPluginUrl($url)
  {
    $this->pluginUrl = $url;
  }

  public function setSupportTitle($supportTitle)
  {
    $this->supportTitle = $supportTitle;
  }

  public function setSupportSubtitle($supportSubtitle)
  {
    $this->supportSubtitle = $supportSubtitle;
  }

  public function displayBanner()
  { ?>
    <style>
      .pp-ads-right-sidebar {
        padding: 0 5px 0 20px;
        width: 240px;
      }

      .pp-ads-right-sidebar .upgrade-btn a {
        background: #FCB223;
        color: #000 !important;
        padding: 9px 12px;
        border-radius: 4px;
        border: 1px solid #fca871;
        text-decoration: none;
        white-space: nowrap;
      }

      .pp-ads-right-sidebar .upgrade-btn a:hover {
        background: #fcca46;
      }

      .pp-ads-right-sidebar h3.hndle {
        font-size: 14px;
        padding: 8px 12px;
        margin: 0;
        line-height: 1.4;
      }

      .pp-ads-right-sidebar .inside ul {
        margin-bottom: 20px;
      }

      .pp-ads-right-sidebar .inside ul li {
        padding-left: 22px;
        font-weight: 600;
        font-size: .9em;
        position: relative;
      }

      .pp-ads-right-sidebar .inside ul li:before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 16px;
        height: 16px;
        background-color: #3C50FF;
        mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z'/></svg>") no-repeat;
      }

      .pp-ads-right-sidebar a.advert-link {
        display: block;
        margin-top: 10px;
        font-size: 1em;
      }

      .pp-ads-right-sidebar .advertisement-box-header {
        background: #655897;
        color: #fff;
      }

      .pp-ads-right-sidebar .advertisement-box-content {
        border: 1px solid #655897;
      }

      @media (max-width: 1079px) {
        .pp-ads-right-sidebar {
          padding: 20px 0;
          flex-basis: auto;
        }
      }

      /* Media query for small screens */
      @media (max-width: 1079px) {
        .pp-ads-right-sidebar {
          padding: 20px 0;
          flex-basis: auto;
        }

        #pp-permissions-wrapper div.pp-options-wrapper {
          flex-basis: 100% !important;
        }
      }
    </style>
    <div class="pp-ads-right-sidebar">
      <div class="advertisement-box-content postbox ppch-advert">
        <div class="postbox-header ppch-advert">
          <h3 class="advertisement-box-header hndle is-non-sortable">
            <span><?php echo esc_html($this->title); ?></span>
          </h3>
        </div>

        <div class="inside ppch-advert">
          <p>
            <?php echo esc_html($this->subtitle); ?>
          </p>
          <ul>
            <?php foreach ($this->features as $feature) : ?>
              <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="upgrade-btn">
            <a href="<?php echo esc_url($this->pluginUrl); ?>" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'press-permit-core'); ?></a>
          </div>
        </div>
      </div>
      <div class="advertisement-box-content postbox ppch-advert">
        <div class="postbox-header ppch-advert">
          <h3 class="advertisement-box-header hndle is-non-sortable">
            <span><?php echo esc_html($this->supportTitle); ?></span>
          </h3>
        </div>

        <div class="inside ppch-advert">
          <p>
            <?php echo esc_html($this->supportSubtitle); ?>
            <a
              class="advert-link" href="<?php echo esc_url($this->pluginSupportUrl); ?>" target="_blank">
              <?php echo esc_html__('Request Support', 'press-permit-core'); ?>
              <svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" width="24" height="24" class="linkIcon">
                <path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
              </svg>
            </a>
          </p>
          <p>
            <?php echo esc_html__('Detailed documentation is also available on the plugin website.', 'press-permit-core'); ?>
            <a
              class="advert-link" href="<?php echo esc_url($this->pluginDocsUrl); ?>" target="_blank">
              <?php echo esc_html__('View Knowledge Base', 'press-permit-core'); ?>
              <svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" width="24" height="24" class="linkIcon">
                <path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
              </svg>
            </a>
          </p>
        </div>
      </div>
    </div>
<?php
  }
}
