<?php

class Dwyera_Pinpay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract //Mage_Payment_Model_Method_Cc //
{
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'pinpay';

    protected $_formBlockType = 'pinpay/form';

    //protected $_infoBlockType = 'pinpay/info';

    private static $logFile = 'dwyera_pinpay_controller.log';

    public function assignData($data)
    {
        Mage::log('assignData', Zend_Log::ERR, self::$logFile, true);

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $email = '';
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {

            /* Get the customer data */
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            /* Get the customer's full name */
            $fullname = $customer->getName();
            /* Get the customer's first name */
            $firstname = $customer->getFirstname();
            /* Get the customer's last name */
            $lastname = $customer->getLastname();
            /* Get the customer's email address */
            $email = $customer->getEmail();

        }

        // Grab the authorised card token and customer IP
        $this->getInfoInstance()->setAdditionalInformation("card_token", $data->getCardToken());
        $this->getInfoInstance()->setAdditionalInformation("ip_address", $data->getIpAddress());

        return $this;
    }

    /*public function initialize($paymentAction, $stateObject)
    {
    }*/


//    public function validate()
//    {
//        //Mage::log('validate'.$this->getA(), Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//        parent::validate();
//        //Mage::log('2', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//        //$info = $this->getInfoInstance();
//
//        //$no = $info->getCheckNo();
//        //$date = $info->getCheckDate();
//
//        $errorMsg = false;
//
//        //Mage::log('234'.$no, Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//
//        if (empty($no) || empty($date)) {
//            Mage::log('empty', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//            $errorCode = 'invalid_data';
//            $errorMsg = $this->_getHelper()->__('Check No and Date are required fields');
//        }
//        else {
//            Mage::log('not empty', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//        }
//
//        if ($errorMsg) {
//            Mage::log('empty2', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//            Mage::throwException($errorMsg);
//        }
//        Mage::log('done', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
//        return $this;
//    }

    /**
     * Send authorize request to gateway
     *
     * @param  Mage_Payment_Model_Info $payment
     * @param  decimal $amount
     * @return Dwyera_Pinpay_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount)
    {

        Mage::log('authorize', Zend_Log::ERR, self::$logFile, true);

        if ($amount <= 0) {
            Mage::log('exp amt lt 0', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
            Mage::throwException(Mage::helper('pinpay')->__('Invalid amount for authorization.'));
        }

        $email = '';

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            /* Get the customer data */
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $email = $customer->getEmail();

        }

        $this->_postRequest($payment->getAdditionalInformation('card_token'),
            $payment->getAdditionalInformation('ip_address'), $amount, $email);

        return $this;
    }


    /**
     * Post request to gateway and return response
     *
     * @param string $token
     * @param string $ip
     * @param decimal $amount
     * @param string $email
     * @return boolean
     */
    protected function _postRequest($token, $ip, $amount, $email)
    {

        $client = new Varien_Http_Client();

        //$uri = $this->getConfigData('cgi_url');
        // Get/Store this in database
        $client->setUri("https://test-api.pin.net.au/1/charges");
        $client->setConfig(array(
            'maxredirects' => 0,
            'timeout' => 30,
            //'ssltransport' => 'tcp',
        ));

        $client->setAuth("xmAoHTvJ4GwqnwQdA_JAbQ", '');
        $client->setMethod($client::POST);
        $client->setParameterPost("email", $email);
        $client->setParameterPost("description", 'description');
        // TODO ensure integer
        $client->setParameterPost("amount", $amount * 100);
        $client->setParameterPost("ip_address", $ip);
        $client->setParameterPost("card_token", $token);

        Mage::log("request: $email $amount $ip $token", Zend_Log::ERR, "dwyera_pinpay_controller.log", true);

        try {
            $response = $client->request();
            $resStr = $response->asString();
            Mage::log('response' . $resStr . ':' . $response->getMessage() . ':' . $response->getStatus(), Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
        } catch (Exception $e) {
            $debugData['result'] = $e->getMessage();
            $this->_debug($debugData);
            Mage::throwException((Mage::helper('pinpay')->__($e->getMessage())));
        }

        return true;

    }

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;
//
//    /**
//     * Can authorize online?
//     */
    protected $_canAuthorize = true;
//
//    /**
//     * Can capture funds online?
//     */
//    protected $_canCapture              = true;
//
//    /**
//     * Can capture partial amounts online?
//     */
//    protected $_canCapturePartial       = false;
//
//    /**
//     * Can refund online?
//     */
    protected $_canRefund = true;
//
//    /**
//     * Can void transactions online?
//     */
//    protected $_canVoid                 = true;
//
//    /**
//     * Can use this payment method in administration panel?
//     */
//    protected $_canUseInternal          = true;
//
//    /**
//     * Can show this payment method as an option on checkout payment page?
//     */
    protected $_canUseCheckout = true;
//
//    /**
//     * Is this payment method suitable for multi-shipping checkout?
//     */
    protected $_canUseForMultishipping = true;
//
//    /**
//     * Can save credit card information for future processing?
//     */
//    protected $_canSaveCc = false;

}
