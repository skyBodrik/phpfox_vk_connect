<?php
$installer = new Core\App\Installer();
$installer->onInstall(function() use ($installer) {
    (new \Apps\PHPfox_Vk\Installation\Version\v1())->process();
});
