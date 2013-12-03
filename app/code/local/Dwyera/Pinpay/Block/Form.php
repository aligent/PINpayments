<?php
class Dwyera_Pinpay_Block_Form extends Mage_Payment_Block_Form_Cc
{
    /** @var  $model Dwyera_Pinpay_Model_PaymentMethod */
    private $model;

    protected function _prepareLayout()
    {
        $this->model = Mage::getModel('pinpay/PaymentMethod');
        return parent::_prepareLayout();
    }

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
        return $this->model->getPublishableKey();
    }

}
