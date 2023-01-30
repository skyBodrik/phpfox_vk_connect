<?php
if (setting('m9_vk_enabled')) {
    echo '<div class="vk_login">';
    echo '<span class="vk_login_go"><span class="core-vk-item-vk-icon"><img src="' . Phpfox::getParam('core.path_actual') . 'PF.Site/Apps/core-vk/assets/images/vk_logo.png"></img></span>' . _p('sign_in_with_vk') . '</span>';
    echo '</div>';
}
