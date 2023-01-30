<?php

use Apps\PHPfox_Vk\Model\Service;

Phpfox::getLib('module')
    ->addAliasNames('pvk', 'PHPfox_Vk')
    ->addServiceNames([
        'pvk.callback' => Apps\PHPfox_Vk\Service\Callback::class,
    ]);
/**
 * Using the Event handler we add JS & CSS to the <head></head>
 */
new Core\Event([
    // event to attach to
    'lib_phpfox_template_getheader' => function (Phpfox_Template $Template) {
        if (!setting('m9_vk_enabled')) {
            $Template->setHeader('<script>var Vk_Login_Disabled = true;</script>');
            $Template->setHeader('<style>.vk_login_go, #header_menu #js_block_border_user_login-block form > .table:first-of-type:before {display:none !important;} #header_menu #js_block_border_user_login-block .title { margin-bottom: 0px; }</style>');
        }
        if ($cached = storage()->get('vk_user_notice_' . user()->id)) {
            storage()->del('vk_user_notice_' . user()->id);
            $sHtml = '<div>' . _p('you_just_signed_up_successfully_with_email_email',
                    ['email' => $cached->value->email]) . '</div>';
            $sHtml .= '<div>' . _p('click_here_to_change_your_password', ['link' => url('user/setting')]) . '</div>';
            $Template->setHeader('<script>var vk_show_notice = false; $Behavior.onReadyAfterLoginVK = function(){ setTimeout(function(){if(Fb_show_notice) return; Fb_show_notice = true; tb_show(\'' . _p('notice_uppercase') . '\',\'\',\'\',\'' . $sHtml . '\');$(\'#\'+$sCurrentId).find(\'.js_box_close:first\').show();},200);}</script>');
        }
    }
]);
// Make sure the app is enabled
if (!setting('m9_vk_enabled')) {
    return;
}

if (auth()->isLoggedIn() && ($cached = storage()->get('vk_force_email_' . user()->id) || substr(user()->email,
            -3) == '@vk' || substr(user()->email, -13) == '@vk.com')
    && request()->segment(1) != 'vk'
    && request()->segment(2) != 'email'
    && request()->segment(1) != 'logout'
    && request()->segment(1) != 'logout'
    && (request()->segment(1) != 'user' || request()->segment(2) != 'logout')) {
    url()->send('/vk/email');
}

route('/vk/email', function () {
    auth()->membersOnly();

    if (request()->isPost()) {
        $val = request()->get('val');
        $validator = validator()->rule('email')->email();
        if (empty($val['email'])) {
            error(_p('provide_your_email'));
        }
        if ($validator->make()) {
            $users = db()->select('COUNT(*)')->from(':user')->where(['email' => db()->escape($val['email'])])->execute('getField');
            if ($users) {
                error(_p('Email is already in use.'));
            }
            if (!Phpfox::getService('ban')->check('email', $val['email'])) {
                error(_p('this_email_is_not_allowed_to_be_used'));
            }

            db()->update(':user', ['email' => $val['email']], ['user_id' => user()->id]);

            //Send welcome email
            Phpfox::getLib('mail')
                ->to(user()->id)
                ->subject(['welcome_email_subject', ['site' => Phpfox::getParam('core.site_title')]])
                ->message(['welcome_email_content'])
                ->sendToSelf(true)
                ->send();

            storage()->del('vk_force_email_' . user()->id);

            //Set cached to show popup notify
            storage()->set('vk_user_notice_' . user()->id, ['email' => $val['email']]);

            $url = '';
            if (Phpfox::getParam('user.redirect_after_signup')) {
                $url = Phpfox::getParam('user.redirect_after_signup');
            }
            url()->send($url, [], _p('thank_you_for_adding_your_email'));
        }
    }

    section(_p('Active Email'), '/vk/email');

    $email = user()->email;
    if (substr($email, -3) == '@vk' || substr($email, -13) == '@vk.com') {
        $email = '';
    }

    return view('email.html', [
        'email' => $email
    ]);
});

// We override the main settings page since their account is connected to VK
$Url = new Core\Url();
if (Phpfox::isUser() && $Url->uri() == '/user/setting/' && substr(Phpfox::getUserBy('email'), -3) == '@vk') {
    (new Core\Route('/user/setting'))->run(function (\Core\Controller $Controller) {
        return $Controller->render('setting.html');
    });
}

/**
 * Controller for the VK login routine
 */
(new Core\Route('/vk/login'))->run(function (\Core\Controller $Controller) {
    $oauth = new VK\OAuth\VKOAuth();
    $clientId = setting('m9_vk_app_id');
    $redirectUri = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/index.php/vk/auth';
    $display = VK\OAuth\VKOAuthDisplay::PAGE;
    $scope = [VK\OAuth\Scopes\VKOAuthUserScope::EMAIL];
    $state = 'secret_state_code';
    $loginUrl = $oauth->getAuthorizeUrl(VK\OAuth\VKOAuthResponseType::CODE, $clientId, $redirectUri, $display, $scope, $state);

    header('Location: ' . $loginUrl);
    exit;
});

/**
 * Auth routine for VK Connect. This is where we either create the new user or log them in if they are already a user.
 */
(new Core\Route('/vk/auth'))->run(function (\Core\Controller $Controller) {
    $oauth = new VK\OAuth\VKOAuth();
    $clientId = setting('m9_vk_app_id');
    $clientSecret = setting('m9_vk_app_secret');
    $redirectUri = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/index.php/vk/auth';
    $code = $_GET['code'];

    $response = $oauth->getAccessToken($clientId, $clientSecret, $redirectUri, $code);

    try {
        $accessToken = $response['access_token'];
        $vkUserId = $response['user_id'];
        $vkUserEmail = $response['email'];
    }
    catch (Exception $exception) {
        Phpfox::getLog('vk.log')->error('Get Access Token Failed' . $exception->getMessage());
    }

    if (!empty($accessToken) && !empty($vkUserId)) {
        // Save the access token to a session and redirect
        $vk = new VK\Client\VKApiClient();
        $response = $vk->users()->get($accessToken, [
            'user_ids'  => [$vkUserId],
            'fields'    => ['screen_name', 'nickname', 'city', 'email', 'sex', 'photo', 'photo_medium'],
        ]);

        if (count($response) === 1 && isset($response[0])) {
            $Service = new Service();
            $result = $Service->create([
                'email' => $vkUserEmail,
                'vkUserId' => $vkUserId,
                'first_name' => $response[0]['first_name'],
                'last_name' => $response[0]['last_name'],
                'nick_name' => $response[0]['screen_name'],
                'gender' => $response[0]['sex'],
                'photo' => [
                    'tiny' => $response[0]['photo'] ?? null,
                    'medium' => $response[0]['photo_medium'] ?? null,
                ],
            ]);

            $sUrl = '';
            if ($result) {
                if (Phpfox::getParam('core.redirect_guest_on_same_page')) {
                    $sUrl = Phpfox::getLib('session')->get('redirect');
                    if (is_bool($sUrl)) {
                        $sUrl = '';
                    }
                    if (empty($sUrl) && !empty($sMainUrl)) {
                        $sUrl = $sMainUrl;
                    }
                    if ($sUrl && filter_var($sUrl, FILTER_VALIDATE_URL)) {
                        $aParts = explode('/', trim($sUrl, '/'));
                        if (isset($aParts[0])) {
                            $aParts[0] = Phpfox_Url::instance()->reverseRewrite($aParts[0]);
                        }
                        if (isset($aParts[0]) && !Phpfox::isModule($aParts[0])) {
                            $aUserCheck = Phpfox::getService('user')->getByUserName($aParts[0]);
                            if (isset($aUserCheck['user_id'])) {
                                if (isset($aParts[1]) && !Phpfox::isModule($aParts[1])) {
                                    $sUrl = '';
                                }
                            } else {
                                $sUrl = '';
                            }
                        }
                    }
                }
                if (is_numeric($result)) {
                    //Sign up
                    if (Phpfox::getParam('user.redirect_after_signup')) {
                        $sUrl = Phpfox::getParam('user.redirect_after_signup');
                    }
                } elseif (Phpfox::getParam('user.redirect_after_login')) {
                    //Log in
                    $sUrl = Phpfox::getParam('user.redirect_after_login');
                }

                $Controller->url->send($sUrl);
            }
        }
    } else {
        $Controller->url->send('user.register', [], _p('permissions_denied'));
    }
});
