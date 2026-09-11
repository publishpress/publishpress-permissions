<?php

namespace PublishPress\Permissions\UI;

class HintsPro 
{
    public static function proPromo()
    {
        presspermit_enqueue_admin_script();
    ?>
<div style="margin-top:5px">
<a href="#pp-pro-info"><?php esc_html_e('Show list of Permissions Pro features and screencasts', 'press-permit-core'); ?></a>
</div>

<?php
$img_url = PRESSPERMIT_URLPATH . '/common/img/';
$lang_id = 'press-permit-core';
?>
<style>
#pp_features {
    text-align: left;
    border: 1px solid #eee;
    margin: 10px 20px 20px 20px;
    background-color: white
}

div.pp-logo,
div.pp-logo img {
    text-align: left;
    clear: both
}

ul.pp-features {
    list-style: none;
    padding-top: 10px;
    text-align: left;
    margin-left: 50px;
    margin-top: 0;
}

ul.pp-features li:before {
    content: "\2713\0020";
}

ul.pp-features li {
    padding-bottom: 5px
}

img.cme-play {
    margin-bottom: -3px;
    margin-left: 5px;
}

ul.pro-pplinks {
    margin-top: 0;
    margin-left: 20px;
    width: 100%;
}

ul.pro-pplinks li {
    display: inline;
    margin: 0 3px 0 3px;
}

ul.pro-pplinks li.spacer {
    font-size: 1.5em;
}
</style>

<div id="pp_features" style="display:none">
<div class="pp-logo"><a href="https://publishpress.com">
        <img src="<?php echo esc_url($img_url); ?>pp-logo.png"/></a>

    <ul class="pp-features">

        <li>
            <?php esc_html_e("Customize editing permissions per-category or per-post", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=0yOEBD8VE9c&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=3"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Limit category/term assignment and page parent selection", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=QqvtxrqLPwY&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=4"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("File Access: regulate direct access to uploaded files", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=kVusrdlgSps&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=15"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Hidden Content Teaser", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=d_5r8NKjxDQ&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=9"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("bbPress: customize viewing, topic creation or reply submission permissions per-forum", $lang_id); ?></li>

        <li>
            <?php esc_html_e("Date-limited membership in Permissions Groups", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=hMOVvCy_9Ws&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=7"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Custom Post Visibility statuses, fully implemented throughout wp-admin", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=vM3Iwt3Jdak&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=6"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Custom Moderation statuses for access-controlled, multi-step publishing workflow", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=v8VyKP3rIvk&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=8"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Regulate permissions for PublishPress post statuses", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=eeZ6CBC5kQI&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=11"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Customize the moderated editing of published content with Revisionary", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=kCD6HQAjUXs&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=12"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("Grant supplemental content permissions to a BuddyPress group", $lang_id); ?>
            <a href="https://www.youtube.com/watch?v=oABIT7wki_A&list=PLyelWaWwt1HxuwrZDRBO_c70Tm8A7lfb3&index=14"
            target="_blank">
                <img class="cme-play" src="<?php echo esc_url($img_url); ?>play.png"/></a></li>

        <li>
            <?php esc_html_e("WPML integration to mirror permissions to translations", $lang_id); ?>
        </li>

        <li>
            <?php esc_html_e("Help ticket system", $lang_id); ?>
        </li>
    </ul>

    <ul class="pro-pplinks" style="display:none">
        <li><a class="pp-screencasts" href="https://publishpress.com/permissions-start/" target="_blank"><?php esc_html_e('Knowledge Base', 'press-permit-core'); ?></a></li>
        <li class="spacer">&bull;</li>
        <li><a href="https://publishpress.com/wp-content/uploads/2020/03/RoleScoper-Permissions-Feature-Grid.pdf" target="_blank"><?php esc_html_e('Detailed Feature Grid', 'press-permit-core'); ?></a></li>
        <li class="spacer">&bull;</li>
        <li><a href="https://publishpress.com/contact/" target="_blank"><?php esc_html_e('Contact Us', 'press-permit-core'); ?></a></li>
        <li class="spacer">&bull;</li>
        <li><a href="https://publishpress.com/pricing/" target="_blank"><?php esc_html_e('Purchase', 'press-permit-core'); ?></a></li>
        <li class="spacer">&bull;</li>
        <li><a href="#pp-pro-hide"><?php esc_html_e('Hide', 'press-permit-core'); ?></a></li>
    </ul>

</div>
        <?php
    }
}
