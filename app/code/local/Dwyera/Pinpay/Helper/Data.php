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
}
	 