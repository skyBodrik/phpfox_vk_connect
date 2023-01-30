<?php

namespace Apps\PHPfox_Vk\Model;

use Core\Hash as Hash;
use Core\Model as Model;
use Phpfox;
use Phpfox_Error;
use Phpfox_Plugin;
use VK\Actions\Enums\AccountSex;

/**
 * Service class for Vk Connect App
 *
 * @package Apps\PHPfox_Vk\Model
 */
class Service extends Model
{

    /**
     * Create a new user or log them in if they exist
     *
     * @param array $userData
     * @return bool|int
     * @throws \Exception
     */
    public function create(array $userData)
    {
        $email = $userData['email'];
        $firstName = $userData['first_name'];
        $lastName = $userData['last_name'];
        $birthday = $userData['birthday'];
        $city = $userData['city']['title'] ?? null;
        $country = $userData['country']['title'] ?? null;
        $url = null;
        $blankEmail = false;
        $bSkipPass = false;
        $bReturn = true;

        $vkId = $userData['vkUserId'];
        if (!$email) {
            $email = $vkId . '@vk';
            $blankEmail = true;
        }

        $cached = storage()->get('vk_users_' . $vkId);
        if ($cached) {
            $user = $this->db->select('*')->from(':user')->where(['user_id' => $cached->value->user_id])->get();
            if (isset($user['email'])) {
                $email = $user['email'];
            } else {
                storage()->del('vk_users_' . $vkId);
            }
        } else {
            $user = $this->db->select('*')->from(':user')->where(['email' => $email])->get();
        }

        if (isset($user['user_id'])) {
            //don't reset current user password if account existed
            $_password = $user['password'];
            $bSkipPass = true;
//
//            if ($userData['gender'] == AccountSex::MALE) {
//                $iGender = 1;
//            } elseif ($userData['gender'] == AccountSex::FEMALE) {
//                $iGender = 2;
//            } else {
//                $iGender = 0;
//            }
//
//            define('PHPFOX_IS_CUSTOM_FIELD_UPDATE', true);
//            $aUser = [
//                'country_iso' => 'RU',
//                'city_location' => $city,
//                'gender' => $iGender
//            ];
//
//            $bReturnUser = Phpfox::getService('user.process')->update($user['user_id'], $aUser);


            //(($sPlugin = Phpfox_Plugin::get('user.service_process_add_extra')) ? eval($sPlugin) : false);

            //var_dump($bReturnUser); die;
        } else {
            if (!Phpfox::getParam('user.allow_user_registration')) {
                return false;
            }
            if (Phpfox::getParam('user.invite_only_community') && !Phpfox::getService('invite')->isValidInvite($user['email'])) {
                return false;
            }
            $_password = $vkId . uniqid();
            $password = (new Hash())->make($_password);
            if ($userData['gender'] == AccountSex::MALE) {
                $iGender = 1;
            } elseif ($userData['gender'] == AccountSex::FEMALE) {
                $iGender = 2;
            } else {
                $iGender = 0;
            }

            $aInsert = [
                'user_group_id' => Phpfox::getParam('user.on_register_user_group', NORMAL_USER_ID),
                'email' => $email,
                'password' => $password,
                'gender' => $iGender,
                'birthday' => $birthday,
                'full_name' => ($firstName . ' ' . $lastName),
                'user_name' => ($url === null ? 'vk-' . $vkId : str_replace('.', '-', $url)),
                'user_image' => '',
                'joined' => PHPFOX_TIME,
                'last_activity' => PHPFOX_TIME
            ];

            if (Phpfox::getParam('user.approve_users')) {
                $aInsert['view_id'] = '1';// 1 = need to approve the user
            }

            $id = $this->db->insert(':user', $aInsert);
            $bReturn = $id;

            define('PHPFOX_IS_CUSTOM_FIELD_UPDATE', true);
            $aUser = [
                'country_iso' => 'RU',
                'city_location' => $city,
                'gender' => $iGender
            ];

            $bReturnUser = Phpfox::getService('user.process')->update($user['user_id'], $aUser);

            // Get user's avatar
            $avatar = $userData['photo']['medium'];
            if ($avatar) {
                $sImage = fox_get_contents($avatar);
                $sFileName = md5('user_avatar' . time()) . '.jpg';
                $sImagePath = Phpfox::getParam('core.dir_user') . $sFileName;
                file_put_contents($sImagePath, $sImage);
                Phpfox::getService('user.process')->uploadImage($id, true, $sImagePath);
            }

            if ($blankEmail) {
                storage()->set('vk_force_email_' . $id, $vkId);
            } else {
                //Set cache to show popup notify
                storage()->set('vk_user_notice_' . $id, ['email' => $email]);
            }

            storage()->set('vk_users_' . $vkId, [
                'user_id' => $id,
                'email' => $email
            ]);

            //Storage account login by Vk, in the first time this user change password, he/she doesn't need confirm old password.
            storage()->set('vk_new_users_' . $id, [
                'vk_id' => $vkId,
                'email' => $email
            ]);

            $aExtras = array(
                'user_id' => $id
            );

            (($sPlugin = Phpfox_Plugin::get('user.service_process_add_extra')) ? eval($sPlugin) : false);
            
            $tables = [
                'user_activity',
                'user_field',
                'user_space',
                'user_count'
            ];
            foreach ($tables as $table) {
                $this->db->insert(':' . $table, $aExtras);
            }

            $iFriendId = (int)Phpfox::getParam('user.on_signup_new_friend');
            if ($iFriendId > 0 && Phpfox::isModule('friend')) {
                $iCheckFriend = db()->select('COUNT(*)')
                    ->from(Phpfox::getT('friend'))
                    ->where('user_id = ' . (int)$id . ' AND friend_user_id = ' . (int)$iFriendId)
                    ->execute('getSlaveField');

                if (!$iCheckFriend) {
                    db()->insert(Phpfox::getT('friend'), array(
                            'list_id' => 0,
                            'user_id' => $id,
                            'friend_user_id' => $iFriendId,
                            'time_stamp' => PHPFOX_TIME
                        )
                    );

                    db()->insert(Phpfox::getT('friend'), array(
                            'list_id' => 0,
                            'user_id' => $iFriendId,
                            'friend_user_id' => $id,
                            'time_stamp' => PHPFOX_TIME
                        )
                    );

                    if (!Phpfox::getParam('user.approve_users')) {
                        Phpfox::getService('friend.process')->updateFriendCount($id, $iFriendId);
                        Phpfox::getService('friend.process')->updateFriendCount($iFriendId, $id);
                    }
                }
            }

            $iId = $id; // add for plugin use

            if (!defined('PHPFOX_INSTALLER') && !$blankEmail) {
                Phpfox::getLib('mail')
                    ->to($iId)
                    ->subject(['welcome_email_subject', ['site' => Phpfox::getParam('core.site_title')]])
                    ->message(['welcome_email_content'])
                    ->send();
            }

            $this->initDefaultProfileSetting($iId);
            $this->initDefaultNotificationSettings($iId);

            (($sPlugin = Phpfox_Plugin::get('user.service_process_add_end')) ? eval($sPlugin) : false);

            $this->db->insert(':user_ip', [
                    'user_id' => $iId,
                    'type_id' => 'register',
                    'ip_address' => Phpfox::getIp(),
                    'time_stamp' => PHPFOX_TIME
                ]
            );

            //Auto pick a package if required on sign up
            if (!defined('PHPFOX_INSTALLER') && Phpfox::isAppActive('Core_Subscriptions') && Phpfox::getParam('subscribe.enable_subscription_packages') && Phpfox::getParam('subscribe.subscribe_is_required_on_sign_up'))  {
                $aPackages = Phpfox::getService('subscribe')->getPackages(true);
                if (count($aPackages)) {
                    //Get first package
                    $aPackage = $aPackages[0];
                    $iPurchaseId = Phpfox::getService('subscribe.purchase.process')->add([
                        'package_id' => $aPackage['package_id'],
                        'currency_id' => $aPackage['default_currency_id'],
                        'price' => $aPackage['default_cost']
                    ], $iId);
                    $iDefaultCost = (int)str_replace('.', '', $aPackage['default_cost']);
                    if ($iPurchaseId) {
                        if ($iDefaultCost > 0) {
                            define('PHPFOX_MUST_PAY_FIRST', $iPurchaseId);
                            Phpfox::getService('user.field.process')->update($iId, 'subscribe_id', $iPurchaseId);
                        } else {
                            Phpfox::getService('subscribe.purchase.process')->update($iPurchaseId, $aPackage['package_id'], 'completed', $iId, $aPackage['user_group_id']);
                        }
                    }
                }
            }

            if(Phpfox::isAppActive('Core_Activity_Points')) {
                Phpfox::getService('activitypoint.process')->updatePoints($id, 'user_signup');
            }
        }
        Phpfox::getService('user.auth')->login($email, $_password, true, 'email', $bSkipPass);
        if (!Phpfox_Error::isPassed()) {
            $errors = Phpfox_Error::get();
            Phpfox_Error::reset();
            throw new \Exception(implode('', $errors));
        }

        return $bReturn;
    }

    private function initDefaultProfileSetting($iUserId)
    {
        if (method_exists('User_Service_Process', 'initDefaultProfileSetting')) {
            return Phpfox::getService('user.process')->initDefaultProfileSetting($iUserId);
        }

        if (empty($iUserId)) {
            return false;
        }

        $bIsFriendOnly = Phpfox::getParam('core.friends_only_community');
        $sDefaultSettingValue = Phpfox::getParam('user.on_register_privacy_setting');
        switch ($sDefaultSettingValue) {
            case 'network':
                $iPrivacySetting = $bIsFriendOnly ? '2' : '1';
                break;
            case 'friends_only':
                $iPrivacySetting = '2';
                break;
            case 'no_one':
                $iPrivacySetting = '4';
                break;
            default:
                break;
        }

        if (isset($iPrivacySetting)) {
            $aProfiles = Phpfox::massCallback('getProfileSettings');
            $aDefaultConvertedSettingValues = [];
            $aAllowPrivacyList = [];
            $aPrivacy = [];
            foreach ($aProfiles as $aSettings) {
                $aPrivacy = array_merge($aPrivacy, array_keys($aSettings));
                foreach ($aSettings as $settingKey => $aSetting) {
                    $aAllowPrivacyList[$settingKey] = [];
                    if (!isset($aSetting['anyone']) && !$bIsFriendOnly) {
                        $aAllowPrivacyList[$settingKey][] = '0';
                    }
                    if (!isset($aSetting['no_user'])) {
                        if (!isset($aSetting['friend_only']) && (!$bIsFriendOnly || !empty($aSetting['ignore_friend_only']))) {
                            $aAllowPrivacyList[$settingKey][] = '1';
                        }
                        if (Phpfox::isModule('friend')) {
                            if (!isset($aSetting['friend']) || $aSetting['friend']) {
                                $aAllowPrivacyList[$settingKey][] = '2';
                            }
                            if (!empty($aSetting['friend_of_friend'])) {
                                $aAllowPrivacyList[$settingKey][] = '3';
                            }
                        }
                    }
                    //No one is always available
                    $aAllowPrivacyList[$settingKey][] = '4';
                    if (isset($aSetting['converted_default_value'])) {
                        if ($sDefaultSettingValue == 'network' && $bIsFriendOnly
                            && isset($aSetting['converted_default_value']['2'])) {
                            //If Friend Only community -> default value should be Community instead of Friends of Friends
                            $aSetting['converted_default_value']['2'] = '1';
                        }
                        $aDefaultConvertedSettingValues[$settingKey] = $aSetting['converted_default_value'];
                    }
                }
            }

            foreach ($aPrivacy as $sPrivacy) {
                $a = explode('.', $sPrivacy);
                if (!isset($a[0]) || !Phpfox::isModule($a[0])) {
                    continue;
                }
                $iDefaultValue = isset($aDefaultConvertedSettingValues[$sPrivacy][$iPrivacySetting]) ? $aDefaultConvertedSettingValues[$sPrivacy][$iPrivacySetting] : $iPrivacySetting;
                if (!in_array($iDefaultValue, $aAllowPrivacyList[$sPrivacy]) && count($aAllowPrivacyList[$sPrivacy])) {
                    $iDefaultValue = $aAllowPrivacyList[$sPrivacy][0];
                }
                db()->insert(':user_privacy', [
                        'user_id'      => $iUserId,
                        'user_privacy' => $sPrivacy,
                        'user_value'   => $iDefaultValue,
                    ]
                );
            }
        }
        return true;
    }

    public function initDefaultNotificationSettings($iId)
    {
        if (!method_exists('Admincp_Service_Setting_Setting', 'getDefaultNotificationSettings')) {
            return false;
        }
        //Add default notification settings
        $aDefaultEmailNotification = Phpfox::getService('admincp.setting')->getDefaultNotificationSettings('email', true, true);
        if (count($aDefaultEmailNotification)) {
            $aDefaultEmailInsert = [];
            foreach ($aDefaultEmailNotification as $sVar => $iValue) {
                $aDefaultEmailInsert[] = [$iId, $sVar, 'email', 0];
            }
            db()->multiInsert(Phpfox::getT('user_notification'), [
                'user_id', 'user_notification', 'notification_type', 'is_admin_default'
            ], $aDefaultEmailInsert);
        }
        $aDefaultSmsNotification = Phpfox::getService('admincp.setting')->getDefaultNotificationSettings('sms', true, true);
        if (count($aDefaultSmsNotification)) {
            $aDefaultSmsInsert = [];
            foreach ($aDefaultSmsNotification as $sVar => $iValue) {
                $aDefaultSmsInsert[] = [$iId, $sVar, 'sms', 0];
            }
            db()->multiInsert(Phpfox::getT('user_notification'), [
                'user_id', 'user_notification', 'notification_type', 'is_admin_default'
            ], $aDefaultSmsInsert);
        }
        return true;
    }
}
