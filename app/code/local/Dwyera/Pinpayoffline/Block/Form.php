<?php
class Dwyera_Pinpayoffline_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();

        $template = $this->setTemplate('pinpayoffline/form/pinpay.phtml');

        if(!Mage::app()->getStore()->isAdmin()) {
            $mark = Mage::getConfig()->getBlockClassName('core/template');
            $mark = new $mark;
            $mark->setTemplate('pinpay/form/mark.phtml');
            // Appends the "Powered by PinPayments logo to payment method description
            $template->setMethodLabelAfterHtml($mark->toHtml());
        }
    }

    /**
     * Gets the publishable key for the PinPayments account
     * @return string
     */
    protected function getPublishableKey() {
        return $this->getMethod()->getPublishableKey();
    }

    /**
     * @return Mage_Sales_Model_Quote_Address
     */
    protected function getBillingAddress() {
        return $this->getMethod()->getInfoInstance()->getQuote()->getBillingAddress();
    }

    protected function getOfflineCardToken() {
        $method = $this->getMethod();
        return $method::OFFLINE_CARD_TOKEN_PLACEHOLDER;
    }

}
