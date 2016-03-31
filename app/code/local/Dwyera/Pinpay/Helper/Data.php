<?php
class Dwyera_Pinpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getScriptUrl(){
        if(Mage::getStoreConfig('payment/pinpay/test')){
            return 'pinpay/pin.test.js';
        }else{
            return 'pinpay/pin.prod.js';
       }
    }

    public function getTimeout() {
        // Get time out from system config
        $iTimeout = Mage::getStoreConfig('payment/pinpay/time_out');
        // default to 30 seconds if not found or value is invalid
        if (!$iTimeout || !is_numeric($iTimeout)) {
            $iTimeout = 30;
        }
        return $iTimeout;
    }

}
	 