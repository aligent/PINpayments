<?php

class Dwyera_Pinpay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{

    const REQUEST_TYPE_AUTH_ONLY    = 'AUTH_ONLY';

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'pinpay';

    protected $_formBlockType = 'pinpay/form';

    // Disabled info block
    //protected $_infoBlockType = 'pinpay/info';

    private static $logFile = 'dwyera_pinpay_controller.log';

    public function assignData($data)
    {
        Mage::log('assignData', Zend_Log::ERR, self::$logFile, true);

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

//        $email = '';
//        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
//
//            /* Get the customer data */
//            $customer = Mage::getSingleton('customer/session')->getCustomer();
//            /* Get the customer's full name */
//            $fullname = $customer->getName();
//            /* Get the customer's first name */
//            $firstname = $customer->getFirstname();
//            /* Get the customer's last name */
//            $lastname = $customer->getLastname();
//            /* Get the customer's email address */
//            $email = $customer->getEmail();
//
//        }

        // Store the authorised card token and customer IP
        $this->getInfoInstance()->setAdditionalInformation("card_token", $data->getCardToken());
        $this->getInfoInstance()->setAdditionalInformation("ip_address", $data->getIpAddress());

        return $this;
    }

    /**
     * Send authorize request to gateway
     *
     * @param  Varien_Object $payment
     * @param  decimal $amount
     * @return Dwyera_Pinpay_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);

        Mage::log("authorize request for $amount", Zend_Log::DEBUG, self::$logFile, true);

        if ($amount <= 0) {
            Mage::log('Expected amount for transaction is zero or below', Zend_Log::ERR, "dwyera_pinpay_controller.log", true);
            Mage::throwException(Mage::helper('pinpay')->__('Invalid amount for authorization.'));
        }

        $email = '';

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            /* Get the customer data */
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $email = $customer->getEmail();
        }

        Mage::log("1", Zend_Log::DEBUG, self::$logFile, true);
        $request = Mage::getModel('pinpay/request');
        $request = $request->setEmail($email)->setAmount($amount)->
            setToken($payment->getAdditionalInformation('card_token'))->
            setCustomerIp($payment->getAdditionalInformation('ip_address'));
        $this->_place($payment, $amount, self::REQUEST_TYPE_AUTH_ONLY, $request);

        return $this;
    }

    /**
     * Gets the PinPayments secret key from the admin config
     * @return string Secret Key
     */
    protected function getSecretKey() {
        //TODO replace with call to admin preferences
        return "xmAoHTvJ4GwqnwQdA_JAbQ";
    }

    /**
     * Send request with new payment to PinPayments gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * @param string $requestType
     * @param Dwyera_Pinpay_Model_Request
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     * @throws InvalidArgumentException
     */
    protected function _place($payment, $amount, $requestType, $request) {

        $payment->setAmount($amount);
        switch($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                $this->_postRequest($request, $requestType);
                break;
            default:
                throw new InvalidArgumentException("Invalid request type of $requestType");
        }
    }

    /**
     * @param Dwyera_Pinpay_Model_Request $request
     * @param $requestType
     * @return bool
     */
    protected function _postRequest(Dwyera_Pinpay_Model_Request $request, $requestType)
    {
        // TODO This method should be made more generic to support the various calls to PinPayments
        $client = new Varien_Http_Client();

        //$uri = $this->getConfigData('cgi_url');
        // TODO Get/Store this URL in database as an admin config option
        $client->setUri("https://test-api.pin.net.au/1/charges");
        $client->setConfig(array(
            'maxredirects' => 0,
            'timeout' => 30,
        ));

        $client->setAuth($this->getSecretKey(), '');
        $client->setMethod($client::POST);
        $client->setParameterPost("email", $request->getEmail());
        $client->setParameterPost("description", 'description');
        $client->setParameterPost("amount", $request->getAmountInCents());
        $client->setParameterPost("ip_address", $request->getIp());
        $client->setParameterPost("card_token", $request->getToken());

        Mage::log("request: $request->getData()", Zend_Log::ERR, "dwyera_pinpay_controller.log", true);

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

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = false;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = false;

    /**
     * Can refund online?
     */
    protected $_canRefund = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid  = false;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping = true;

}
