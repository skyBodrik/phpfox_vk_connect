<?php

namespace Apps\PHPfox_Vk;

use Core\App;
use Core\App\Install\Setting;

/**
 * Class Install
 * @package Apps\PHPfox_Vk
 */
class Install extends App\App
{
    private $_app_phrases = [

    ];

    public $store_id = 0;

    protected function setId()
    {
        $this->id = 'PHPfox_Vk';
    }

    /**
     * Set start and end support version of your App.
     */
    protected function setSupportVersion()
    {
        $this->start_support_version = '1.0.0';
    }

    protected function setAlias()
    {
        $this->alias = 'pvk';
    }

    protected function setName()
    {
        $this->name = _p('vk_app');
    }

    protected function setVersion()
    {
        $this->version = '1.0.0';
    }

    protected function setSettings()
    {
        $this->settings = [
            "m9_vk_enabled" => [
                "var_name" => "m9_vk_enabled",
                "info" => "Vk Login Enabled",
                "type" => Setting\Site::TYPE_RADIO,
                "value" => "0",
            ],
            "m9_vk_app_id" => [
                "var_name" => "m9_vk_app_id",
                "info" => "Vk Application ID",
            ],
            "m9_vk_app_secret" => [
                "var_name" => "m9_vk_app_secret",
                "info" => "Vk App Secret",
            ],
        ];
    }

    protected function setUserGroupSettings()
    {
    }

    protected function setComponent()
    {
    }

    protected function setComponentBlock()
    {
    }

    protected function setPhrase()
    {
        $this->phrase = $this->_app_phrases;
    }

    protected function setOthers()
    {
        $this->_publisher = 'Bodrik';
        $this->_publisher_url = '';
        $this->_apps_dir = 'core-vk';
        $this->admincp_route = \Phpfox::getLib('url')->makeUrl('admincp.app.settings', ['id' => 'PHPfox_Vk']);
    }
}
