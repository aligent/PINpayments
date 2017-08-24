<?php
class Dwyera_Pinpay_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();

        $template = $this->setTemplate('pinpay/form/pinpay.phtml');

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

    /**
     * Get quote from session
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getSessionQuote()
    {
        if (Mage::app()->getStore()->isAdmin()) {
            $oQuote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        } else {
            $oQuote = Mage::getSingleton('checkout/session')->getQuote();
        }
        return $oQuote;
    }

    /**
     * Get store_id from quote
     *
     * @return int|null
     */
    protected function _getQuoteStoreId()
    {
        $oQuote = $this->_getSessionQuote();
        $iStoreId = null;
        if ($oQuote && $oQuote->getId()) {
            $iStoreId = $oQuote->getStoreId();
        }
        return $iStoreId;
    }

    /**
     * Check if cc type is enabled based on the current quote's store
     *
     * @return bool
     */
    public function isCcTypeEnabled()
    {
        // check where is the order being created
        return Mage::getStoreConfigFlag('payment/pinpay/cctypes_enabled', $this->_getQuoteStoreId());
    }

    /**
     * Check if cc type is enabled in frontend checkout
     *
     * @return bool
     */
    public function isCcTypeDisplayedInFrontend()
    {
        // No need to check store_id of current quote for frontend
        return Mage::getStoreConfigFlag('payment/pinpay/cctypes_frontend_enabled');
    }

    /**
     * Check if cc type is enabled in backend checkout
     *
     * @return bool
     */
    public function isCcTypeDisplayedInBackend()
    {
        // Need to check store_id of current quote
        return Mage::getStoreConfigFlag('payment/pinpay/cctypes_backend_enabled', $this->_getQuoteStoreId());
    }

    public function iscustomerTokenizationEnabled()
    {
        // check where is the order being created
        return Mage::getStoreConfigFlag('payment/pinpay/customer_tokenization', $this->_getQuoteStoreId());
    }

}
