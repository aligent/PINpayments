<?php
class Dwyera_Pinpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getScriptUrl(){

        return 'pinpay/hosted_card_fields.js';
        if(Mage::getStoreConfig('payment/pinpay/test')){
            return 'pinpay/pin.test.js';
        }else{
            return 'pinpay/pin.prod.js';
       }
    }
}
	 