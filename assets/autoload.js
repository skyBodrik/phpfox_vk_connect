$Ready(function () {
    console.log('VK Connect');
    /**
     * Loop thru all profile images with a connection to Vk
     */
    $('.image_object:not(.vk_built)[data-object="vk"]').each(function () {
        var t = $(this), src = '//graph.vk.com/' + t.data('src') + '/picture?type=square&width=200&height=200';
        t.addClass('vk_built');
        t.attr('src', src);
    });

    // Add the FB login button
    if (!$('.vk_login_go_cache').length && (typeof(Vk_Login_Disabled) == 'undefined' || !Vk_Login_Disabled)) {
        var l = $('#js_block_border_user_login-block form');
        var logo_href = rtrim(getParam('sBaseURL').replace('index.php', ''), '/')  + '/PF.Site/Apps/core-vk/assets/images/vk_logo.png';
        if (l.length) {
            l.before(
                '<span class="vk_login_go vk_login_go_cache"><span class="core-vk-item-vk-icon"><img src="'+ logo_href + '"></img></span>Vk</span>');
        } else {
            l = $('[data-component="guest-actions"]');
            bootstrapSm = $('.sticky-bar-sm .guest_login_small');
            bootstrapXs = $('.login-menu-btns-xs');
            l.addClass('vk-login-wrapper');
            bootstrapSm.addClass('vk-login-wrapper vk-login-wrapper-sm');
            bootstrapXs.addClass('vk-login-wrapper vk-login-wrapper-xs');
            bootstrapSm.append(
                '<div class="vk-login-header"><span class="vk_login_go vk_login_go_cache"><span class="core-vk-item-vk-icon"><img src="' + logo_href + '"></img></span> <span class="vk-login-label">Vk</span></span></div>');
            l.append(
                '<div class="vk-login-header"><span class="vk_login_go vk_login_go_cache"><span class="core-vk-item-vk-icon"><img src="' + logo_href + '"></img></span> <span class="vk-login-label">Vk</span></span></div>');
            bootstrapXs.append(
                '<div class="vk-login-header"><span class="vk_login_go vk_login_go_cache"><span class="core-vk-item-vk-icon"><img src="' + logo_href + '"></img></span> <span class="vk-login-label">Vk</span></span></div>');
        }
    }

    // Click event to send the user to log into Vk
    $('.vk_login_go').click(function () {
        PF.url.send('/vk/login', true);
    });
});