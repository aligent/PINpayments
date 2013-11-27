<?php

class Dwyera_Pinpay_Model_Request extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('pinpay/request');
    }

    public function getAmountInCents() {
        return $this->getAmount() * 100;
    }
}