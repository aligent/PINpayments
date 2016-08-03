<?php

class Dwyera_Pinpay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY    = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';

    const MAX_REDIRECTS = 0;

    const TIMEOUT = 30;

    const GENERIC_PAYMENT_GATEWAY_ERROR = "Payment gateway error.  Please try again soon.";

    const RESPONSE_APPEND_MSG = ". If you believe this message is incorrect, please contact your bank or our customer service department.";

    const OFFLINE_CARD_TOKEN_PLACEHOLDER = "OFFLINE TRANSACTION";

    const ONLINE = 'online';
    const OFFLINE = 'offline';

    const CONFIG_FLAG_ENV = 'payment/pinpay/test';

    const CONFIG_PREFIX_DESC = 'payment/pinpay/description_prefix';

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'pinpay';

    protected $_formBlockType = 'pinpay/form';

    private static $logFile = 'dwyera_pinpay_controller.log';

    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $cardToken = $data->getCardToken();
        $offlineTransId = $data->getOfflineTransactionId();
        $type = $data->getType();
        if (empty($cardToken)) {
            Mage::log('Payment could not be processed. Missing card token', Zend_Log::ERR, self::$logFile);
            Mage::throwException((Mage::helper('pinpay')->__(self::GENERIC_PAYMENT_GATEWAY_ERROR)));
        }
        // Store the authorised card token and customer IP
        $this->getInfoInstance()->setAdditionalInformation("card_token", $data->getCardToken());

        // Store the offline transaction ID if supplied
        if(Mage::app()->getStore()->isAdmin() && !empty($offlineTransId) && $type == self::OFFLINE) {
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
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return $this|Mage_Payment_Model_Abstract
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);

        if ($amount <= 0) {
            Mage::log('Expected amount for transaction is zero or below', Zend_Log::ERR, self::$logFile);
            Mage::throwException(Mage::helper('pinpay')->__('Invalid amount for authorization.'));
        }

        if(Mage::app()->getStore()->isAdmin() && !is_null($payment->getAdditionalInformation('offline_transaction_id'))) {
            $this->_placeOfflineTransaction($payment, $amount);
        } else {
            $request = $this->_buildRequest($payment, $amount, $this->getCustomerEmail());
            $this->_place($payment, self::REQUEST_TYPE_AUTH_ONLY, $request);
            $payment->setIsTransactionClosed(false);
        }

        return $this;
    }


    /**
     * Send capture request to gateway
     *
     * @param \Mage_Sales_Model_Order_Payment $payment
     * @param  float $amount
     * @return Dwyera_Pinpay_Model_PaymentMethod
     */
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        if ($amount <= 0) {
            Mage::log('Expected amount for transaction is zero or below', Zend_Log::ERR, self::$logFile);
            Mage::throwException(Mage::helper('pinpay')->__('Invalid amount for authorization.'));
        }

        /*
         * If payment method configured for authorize only, the capture method won't be called for transactions recorded as offline
         */
        if(Mage::app()->getStore()->isAdmin() && !is_null($payment->getAdditionalInformation('offline_transaction_id'))) {
            $this->_placeOfflineTransaction($payment, $amount);
        } else {

            $authToken = $this->getPreAuthToken($payment);
            $requestType = is_null($authToken) ? self::REQUEST_TYPE_AUTH_CAPTURE : self::REQUEST_TYPE_CAPTURE_ONLY;

            $request = $this->_buildRequest($payment, $amount, $this->getCustomerEmail());
            $this->_place($payment, $requestType, $request);
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
        $isTesting = Mage::getStoreConfig(self::CONFIG_FLAG_ENV);
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
     * @deprecated Orders no longer placed in fraud review
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
     * @deprecated Orders no longer placed in fraud review
     * @return bool
     */
    public function denyPayment(Mage_Payment_Model_Info $payment){
        parent::denyPayment($payment);
        return true;
    }

    public function getEnvironment() {
        $isTest = Mage::getStoreConfigFlag(Dwyera_Pinpay_Model_PaymentMethod::CONFIG_FLAG_ENV);

        return $isTest ? 'test' : 'live';
    }

    protected function getPreAuthToken($payment) {
        return $payment->getCcTransId();
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @param String $email
     * @return Dwyera_Pinpay_Model_Request
     */
    protected function _buildRequest($payment, $amount, $email)
    {
        $sDescPrefix = Mage::getStoreConfig(self::CONFIG_PREFIX_DESC);

        if($sDescPrefix){
            $sDescPrefix .= ' ';
        }

        $request = Mage::getModel('pinpay/request');
        $request->setEmail($email)->
            setAmount($request::getAmountInCents($amount))->
            setDescription($sDescPrefix . "Quote #:" . $payment->getOrder()->getRealOrderId())->
            setCardToken($payment->getAdditionalInformation('card_token'))->
            setIpAddress($payment->getOrder()->getRemoteIp());

        // Set currency based on order
        $request->setCurrency($payment->getOrder()->getBaseCurrencyCode());

        // Get the transaction ID if set. This will only be the case if a payment has been authorized already
        $token = $this->getPreAuthToken($payment);
        if(!is_null($token)) {
            $request->setAuthToken($token);
        }
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

        switch ($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
            case self::REQUEST_TYPE_AUTH_CAPTURE:
            case self::REQUEST_TYPE_CAPTURE_ONLY:
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
            default:
                // Fraud review orders are now simply treated as a payment failure. No funds are captured by Pin
                Mage::log('Payment could not be processed. ' . $result->getErrorDescription(), Zend_Log::INFO, self::$logFile);
                Mage::throwException((Mage::helper('pinpay')->__($result->getErrorDescription() . self::RESPONSE_APPEND_MSG)));
        }
    }

    /**
     *
     * Requests coming from admin are offline transactions. These don't need to be sent via the PinPayments gateway
     * as they've already been processed.  Simply record the transaction ID supplied by the admin.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @param $transactionId
     */
    protected function _placeOfflineTransaction($payment, $amount) {
        $payment->setAmount($amount);
        $payment->getOrder()->setCustomerNote("Creating offline PinPayments transaction");
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
        $client = new Zend_Http_Client();

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

        if($requestType == self::REQUEST_TYPE_AUTH_CAPTURE || self::REQUEST_TYPE_AUTH_ONLY) {
            $url .= "charges";
            $client->setMethod($client::POST);
            $requestProps = $request->getData();
            //iterate over all params in $request and add them as parameters
            foreach ($requestProps as $propKey => $propVal) {
                $client->setParameterPost($propKey, $propVal);
            }
            if($requestType == self::REQUEST_TYPE_AUTH_CAPTURE) {
                // Tell Pin to immediately capture the funds
                $client->setParameterPost('capture', 'true');
            } else {
                // Tell Pin to only authorize the funds
                $client->setParameterPost('capture', 'false');
            }
        } else if($requestType == self::REQUEST_TYPE_CAPTURE_ONLY) {
            $url .= "charges/" . $request->getAuthToken() . "/capture";
            // Tell Pin to only authorize the funds
            $client->setMethod($client::PUT);
        } else {
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
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

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

    /**
     * Can a payment in a Review state be accepted or cancelled?
     */
    protected $_canReviewPayment = true;

}
