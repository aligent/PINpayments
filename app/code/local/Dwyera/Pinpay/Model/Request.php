<?php

class Dwyera_Pinpay_Model_Request extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
        $this->_init('pinpay/request');
    }

    static public function getAmountInCents($amount) {
        return $amount * 100;
    }
}