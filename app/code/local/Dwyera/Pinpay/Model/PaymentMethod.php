<?php

class Dwyera_Pinpay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{

    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';

    const MAX_REDIRECTS = 0;

    const TIMEOUT = 30;

    const GENERIC_PAYMENT_GATEWAY_ERROR = "Payment gateway error.  Please try again soon.";

    const RESPONSE_APPEND_MSG = ". If you believe this message is incorrect, please contact your bank or our customer service department.";

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
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $cardToken = $data->getCardToken();
        $ipAddress = $data->getIpAddress();
        if (empty($cardToken) || empty($ipAddress)) {
            Mage::log('Payment could not be processed. Missing card token or IP', Zend_Log::ERR, self::$logFile);
            Mage::throwException((Mage::helper('pinpay')->__(self::GENERIC_PAYMENT_GATEWAY_ERROR)));
        }
        // Store the authorised card token and customer IP
        $this->getInfoInstance()->setAdditionalInformation("card_token", $data->getCardToken());
        $this->getInfoInstance()->setAdditionalInformation("ip_address", $data->getIpAddress());

        return $this;
    }

    /**
     * validate
     *
     * Checks form data before it is submitted to processing functions.
     *
     * @return Dwyera_Pinpay_Model_PaymentMethod $this.
     */
    public function validate()
    {
        $email = $this->getCustomerEmail();
        if(empty($email)) {
            Mage::log('Payment could not be processed. Missing card token or IP', Zend_Log::ERR, self::$logFile);
            Mage::throwException((Mage::helper('pinpay')->__(self::GENERIC_PAYMENT_GATEWAY_ERROR)));
        }

        return $this;
    }

    /**
     * Send authorize request to gateway
     *
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @param  float $amount
     * @return Dwyera_Pinpay_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);

        if ($amount <= 0) {
            Mage::log('Expected amount for transaction is zero or below', Zend_Log::ERR, self::$logFile);
            Mage::throwException(Mage::helper('pinpay')->__('Invalid amount for authorization.'));
        }

        $request = $this->_buildRequest($payment, $amount, $this->getCustomerEmail());
        $this->_place($payment, self::REQUEST_TYPE_AUTH_ONLY, $request);

        return $this;
    }

    /**
     * Gets the PinPayments secret key from the admin config
     * @return string Secret Key or empty string if not set
     */
    public function getSecretKey()
    {
        return Mage::getStoreConfig('payment/pinpay/secret_key');
    }

    /**
     * Gets the PinPayments publishable key from the admin config
     * @return string Publishable Key or empty string if not set
     */
    public function getPublishableKey()
    {
        return Mage::getStoreConfig('payment/pinpay/publishable_key');
    }

    /**
     * Returns the correct service URL depending on whether testing mode is enabled
     *
     * @return string The service URL, or empty string if not defined
     */
    public function getServiceURL()
    {
        $isTesting = Mage::getStoreConfig('payment/pinpay/test');
        if ($isTesting == true) {
            return Mage::getStoreConfig('payment/pinpay/testing_url');
        } else {
            return Mage::getStoreConfig('payment/pinpay/production_url');
        }
    }

    protected function _buildRequest($payment, $amount, $email)
    {
        $request = Mage::getModel('pinpay/request');
        $request->setEmail($email)->
            setAmount($request::getAmountInCents($amount))->
            setDescription("Quote #:" . $payment->getOrder()->getRealOrderId())->
            setCardToken($payment->getAdditionalInformation('card_token'))->
            setIpAddress($payment->getAdditionalInformation('ip_address'));
        return $request;
    }

    /**
     * Send request with new payment to PinPayments gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string $requestType
     * @param Dwyera_Pinpay_Model_Request
     * @return boolean Returns true if order was successfully placed
     * @throws Mage_Core_Exception
     * @throws InvalidArgumentException
     */
    protected function _place($payment, $requestType, $request)
    {
        $payment->setAmount($request->getAmount());

        // Simply verify that a valid request type has been sent. Only support authorize at the moment.
        switch ($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                break;
            default:
                throw new InvalidArgumentException("Invalid request type of $requestType");
        }
        $httpResponse = $this->_postRequest($request, $requestType);
        // wrap the gateway response in the pinpay/result model
        /** @var Dwyera_Pinpay_Model_Result $result */
        $result = Mage::getModel("pinpay/result", $httpResponse);

        switch ($result->getGatewayResponseStatus()) {
            case $result::RESPONSE_CODE_APPROVED:
                // Sets the response token
                $payment->setCcTransId('' . $result->getResponseToken());
                $payment->setTransactionId('' . $result->getResponseToken());
                return true;
            case $result::RESPONSE_CODE_SUSP_FRAUD:
                $payment->setIsTransactionPending(true);
                $payment->setIsFraudDetected(true);
                $payment->setCcTransId('' . $result->getErrorToken());
                $payment->setTransactionId('' . $result->getErrorToken());
                return true;
            default:
                Mage::log('Payment could not be processed. ' . $result->getErrorDescription(), Zend_Log::INFO, self::$logFile);
                Mage::throwException((Mage::helper('pinpay')->__($result->getErrorDescription() . self::RESPONSE_APPEND_MSG)));
        }
    }

    /**
     * @param Dwyera_Pinpay_Model_Request $request
     * @param $requestType
     * @throws InvalidArgumentException
     * @throws Mage_Core_Exception
     * @return Zend_Http_Response
     */
    protected function _postRequest(Dwyera_Pinpay_Model_Request $request, $requestType)
    {
        $client = new Varien_Http_Client();

        $url = $this->getServiceURL();

        if(empty($url)) {
            $errMsg = 'Missing service URL.  Please check configuration settings';
            Mage::log($errMsg, Zend_Log::ERR, self::$logFile);
            Mage::throwException((Mage::helper('pinpay')->__($errMsg)));
        }
        // Ensure URL has trailing slash
        if (substr($url, -1) !== "/") {
            $url .= "/";
        }

        $client->setConfig($this->_getHttpConfig());
        $client->setAuth($this->getSecretKey(), '');

        switch ($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                $url .= "charges";
                $client->setMethod($client::POST);

                $requestProps = $request->getData();
                //iterate over all params in $request and add them as parameters
                foreach ($requestProps as $propKey => $propVal) {
                    $client->setParameterPost($propKey, $propVal);
                }
                break;
            default:
                throw new InvalidArgumentException("Invalid request type of $requestType");
        }

        /** @var $response Zend_Http_Response */
        $response = null;
        try {
            $client->setUri($url);
            $response = $client->request();
        } catch (Exception $e) {
            $debugData['result'] = $e->getMessage();
            $this->_debug($debugData);
            Mage::log('Payment could not be processed. ' . $e->getMessage(), Zend_Log::ERR, self::$logFile);
            Mage::throwException((Mage::helper('pinpay')->__(self::GENERIC_PAYMENT_GATEWAY_ERROR)));
        }

        return $response;
    }

    /**
     * Gets the customers email from either the billing info or the session
     */
    protected function getCustomerEmail() {
        $payment = $this->getInfoInstance();
        $email = null;

        if ($payment instanceof Mage_Sales_Model_Order_Payment)
        {
            $email = $payment->getOrder()->getBillingAddress()->getEmail();
        }
        else
        {
            $email = $payment->getQuote()->getBillingAddress()->getEmail();
        }

        if(empty($email) && Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton("customer/session")->getCustomer();
            $email = $customer->getEmail();
        }
        return $email;
    }

    private function _getHttpConfig()
    {
        return array(
            'maxredirects' => self::MAX_REDIRECTS,
            'timeout' => self::TIMEOUT,
        );
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
    protected $_canVoid = false;

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
