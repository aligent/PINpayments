<?php

class Dwyera_Pinpayoffline_Model_PaymentMethod extends Dwyera_Pinpay_Model_PaymentMethod
{
    const OFFLINE_CARD_TOKEN_PLACEHOLDER = "OFFLINE TRANSACTION";

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'pinpay_offline';

    protected $_formBlockType = 'pinpayoffline/form';

    private static $logFile = 'dwyera_pinpay_offline_controller.log';

    public function assignData($data)
    {
        parent::assignData($data);
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $offlineTransId = $data->getOfflineTransactionId();

        // Store the offline transaction ID if supplied
        if(Mage::app()->getStore()->isAdmin() && !empty($offlineTransId)) {
            $this->getInfoInstance()->setAdditionalInformation("offline_transaction_id", $offlineTransId);
        }

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

        if(Mage::app()->getStore()->isAdmin()) {
            $this->_placeOfflineTransaction($payment, $amount);
        } else {
            $request = $this->_buildRequest($payment, $amount, $this->getCustomerEmail());
            $this->_place($payment, self::REQUEST_TYPE_AUTH_ONLY, $request);
        }

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

    /**
     * Accepts an order in review by placing it back into the Pending state where it can
     * then be processed manually.  No action is taken on the actual PinPayments charge. The Magento admin is
     * responsible for reprocessing the transaction.
     *
     * @param Mage_Payment_Model_Info $payment
     * @return bool
     */
    public function acceptPayment(Mage_Payment_Model_Info $payment){
        parent::acceptPayment($payment);
        $message = Mage::helper('pinpay')->__('Order returned to Pending status.
            Please reprocess this transaction manually through the PIN Payments portal before progressing this order.');
        $payment->getOrder()->setState(Mage_Sales_Model_Order::STATE_NEW, true, $message, false);
        $payment->setPreparedMessage($message);
        Mage::getSingleton('adminhtml/session')->addWarning($message);
        return false;
    }

    /**
     *
     * Cancels an order under review. No action is taken on the actual PinPayments transaction, as any PinPayments charges
     * that are flagged as fraudulent are immediately denied.  This method simply cancels the Magento order.
     *
     * @param Mage_Payment_Model_Info $payment
     * @return bool
     */
    public function denyPayment(Mage_Payment_Model_Info $payment){
        parent::denyPayment($payment);
        return true;
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
     *
     * Requests coming from admin are offline transactions. These don't need to be sent via the PinPayments gateway
     * as they've already been processed.  Simply record the transaction ID supplied by the admin.
     *
     * @param $payment
     * @param $amount
     * @param $transactionId
     */
    protected function _placeOfflineTransaction($payment, $amount) {
        $payment->setAmount($amount);

        $transactionId = $payment->getAdditionalInformation('offline_transaction_id');

        $payment->setCcTransId($transactionId);
        $payment->setTransactionId($transactionId);
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
            $email = $payment->getOrder()->getCustomerEmail();
        }
        else
        {
            $email = $payment->getQuote()->getCustomerEmail();
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
    protected $_isGateway = false;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = false;

}
