<?php
class Dwyera_Pinpay_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();

        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('pinpay/form/mark.phtml');
        $test = $mark->toHtml();
        $this->setTemplate('pinpay/form/pinpay.phtml')->setMethodLabelAfterHtml($mark->toHtml());
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
