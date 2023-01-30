<?php

namespace Apps\PHPfox_Vk\Service;

use Phpfox_Service;

defined('PHPFOX') or exit('NO DICE!');

class Callback extends Phpfox_Service
{
    public function onDeleteUser($iUser)
    {
        return;
        $sFilename = $this->database()->select('file_name')
            ->from(':cache')
            ->where('cache_data LIKE \'%"user_id":' . (int)$iUser . ',%\' AND file_name LIKE \'vk_users_%\'')
            ->executeField();
        storage()->del($sFilename);
        storage()->del('vk_new_users_' . (int)$iUser);
        storage()->del('vk_force_email_' . (int)$iUser);
        storage()->del('vk_user_notice_' . (int)$iUser);
    }
}

