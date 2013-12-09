<?php
class Dwyera_Pinpay_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();
        Mage::log('payment form block', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
        $this->setTemplate('pinpay/form/pinpay.phtml');
    }

    /**
     * Gets the publishable key for the PinPayments account
     * @return string
     */
    protected  function getPublishableKey() {
        return $this->getMethod()->getPublishableKey();
    }

    /**
     * @return Mage_Sales_Model_Quote_Address
     */
    protected function getBillingAddress() {
        return $this->getMethod()->getInfoInstance()->getQuote()->getBillingAddress();
    }

}
