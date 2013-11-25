<?php
class Dwyera_Pinpay_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('pinpay/form/pinpay.phtml');
    }
}
