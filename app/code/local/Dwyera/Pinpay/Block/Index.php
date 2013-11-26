<?php
class Dwyera_Pinpay_Block_Index extends Mage_Core_Block_Template
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('pinpay/form/pinpay.phtml');

    }
}