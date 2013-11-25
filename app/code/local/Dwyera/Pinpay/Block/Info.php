<?php
class Dwyera_Pinpay_Block_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $info = $this->getInfo();
        $addInfo = $this->getInfo()->getAdditionalInformation();
        $transport = new Varien_Object();
        $transport = parent::_prepareSpecificInformation($transport);
        $transport->addData(array(
            Mage::helper('payment')->__('Check No#') => 'empty', //$info->getCheckNo(),
            Mage::helper('payment')->__('Check Date') => 'empty' //$info->getCheckDate()
        ));
        return $transport;
    }
}