<?php

namespace Apps\PHPfox_Vk\Installation\Version;

class v1
{
    public function process()
    {
        db()->delete(':setting', 'product_id= "PHPfox_Vk" AND var_name = "m9_vk_require_email"');

    }
}
