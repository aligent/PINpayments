<?php

class Dwyera_Pinpay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY    = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';

    const REQUEST_TYPE_REFUND = 'REFUND';
    const REQUEST_TYPE_CUSTOMER = 'CUSTOMER';

    const MAX_REDIRECTS = 0;

    const TIMEOUT = 30;

    const GENERIC_PAYMENT_GATEWAY_ERROR = "Payment gateway error.  Please try again soon.";

    const RESPONSE_APPEND_MSG = ". If you believe this message is incorrect, please contact your bank or our customer service department.";

    const OFFLINE_CARD_TOKEN_PLACEHOLDER = "OFFLINE TRANSACTION";

    const ONLINE = 'online';
    const OFFLINE = 'offline';

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

        //Check if customer tokenization is enabled.
        $vClassName = Mage::getConfig()->getBlockClassName('pinpay/form');
        $block = new $vClassName();
        $customerTokenizationEnabled = $block->iscustomerTokenizationEnabled();
        $customerToken = $data->getCustomerToken();

        //To Check if the customer is logged in.
        $customerData = $this->getCustomer();

        //Get the card Token from data
        $cardToken = $data->getCardToken();

        //Check if customer token is enabled, customer is logged in and the card token is not the same as the one in database.
        /* Comparing the card token with existing token to make sure the following things donot happen
        1. The unnecessary API calls to the Pinpayment API is avoided if the card token is same
         */
        if($customerTokenizationEnabled && $customerData && $customerData->getData('pinpayment_card_token') != $cardToken){
            if ($data->getData('card_action') == "save_card" &&  $customerToken =="") {
                $customerTokenDetails = $this->getCustomerToken($data);
                $customerToken = $customerTokenDetails->getcustomerToken();
                if ($customerToken != "") {
                    //For first time save all details like customer token, display number and card token to database.
                    $customerData->setData('pinpayment_customer_token', $customerToken);
                    $customerData->setData('pinpayment_card_display_number', $customerTokenDetails->getPrimaryCardDisplayNumber());
                    $customerData->setData('pinpayment_card_token', $customerTokenDetails->getCardToken());
                    $customerData->save();
                }
            //Update the existing customer token with new card details.
            }else if ($customerToken!="" && $data->getData('card_action') == "update_new_card"){
                $customerTokenDetails = $this->updateCustomerDetails($data);
                $customerToken = $customerTokenDetails->getcustomerToken();
                if ($customerToken != "") {
                    $customerData->setData('pinpayment_card_display_number', $customerTokenDetails->getPrimaryCardDisplayNumber());
                    $customerData->setData('pinpayment_card_token', $customerTokenDetails->getCardToken());
                    $customerData->save();
                }
            }
        }



        $ipAddress = $data->getIpAddress();
        $offlineTransId = $data->getOfflineTransactionId();
        $type = $data->getType();
        
        //Display error only if either card/customer token is empty. If anyone of them is null, then the IP address should be checked. 
        //The code will need either cardToken or customerToken for processing transaction. 
        if ((empty($cardToken) && empty($customerToken)) || empty($ipAddress)) {
            Mage::log('Payment could not be processed. Missing card token or IP', Zend_Log::ERR, self::$logFile);
            Mage::throwException((Mage::helper('pinpay')->__(self::GENERIC_PAYMENT_GATEWAY_ERROR)));
        }
        // Store the authorised card token or customer token and customer IP
        if($customerToken !="" && $customerData!= false && $customerTokenizationEnabled){
            $this->getInfoInstance()->setAdditionalInformation("customer_token", $customerToken);
        }else{
            $this->getInfoInstance()->setAdditionalInformation("card_token", $data->getCardToken());
        }

        $this->getInfoInstance()->setAdditionalInformation("ip_address", $data->getIpAddress());
        $this->getInfoInstance()->setData("cc_type", $data->getCcType());

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
     * @param $data
     * @return Dwyera_Pinpay_Model_Result
     * 
     * Using this function to get the customer token and then use the customer token for future purchases. 
     */
    public function getCustomerToken($data){
        $request =  $this->_buildCustomerRequest($data);
        $httpResponse = $this->_postRequest($request, self::REQUEST_TYPE_CUSTOMER);
        $result = Mage::getModel("pinpay/result", $httpResponse);

        return $result;
    }

    /**
     * @param $data
     * @return Dwyera_Pinpay_Model_Result
     *
     * Use this function to update the primary card using new card token
     */
    public function updateCustomerDetails($data){
        $request =  $this->_buildCustomerRequest($data, true);
        $httpResponse = $this->_postRequest($request, self::REQUEST_TYPE_CUSTOMER);
        $result = Mage::getModel("pinpay/result", $httpResponse);

        return $result;
    }


    /**
     * @param $data
     * @return Dwyera_Pinpay_Model_Request
     * 
     * This function is used to build the customer request for API call to get the customer token .
     */
    protected function _buildCustomerRequest($data, $customer_token=false) {
        $request = Mage::getModel('pinpay/request');
        $request->setEmail($this->getCustomerEmail());
        $request->setCardToken($data->getData('card_token'));
        if($customer_token){
            $request->setCustomerToken($data->getData('customer_token'));
        }
        return $request;
    }

    /**
     * @return bool|Mage_Customer_Model_Customer
     * 
     * This just returns customer data if they are logged in. 
     */
    protected function getCustomer() {

        if(Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton("customer/session")->getCustomer();
            return $customer;
        }
        return false;

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
        $storeCode = null;
        if(Mage::app()->getStore()->isAdmin()) {
            $storeCode = Mage::getSingleton('adminhtml/session_quote')->getStore()->getCode();
        }
        return Mage::getStoreConfig('payment/pinpay/secret_key', $storeCode);
    }

    /**
     * Gets the PinPayments publishable key from the admin config
     * @return string Publishable Key or empty string if not set
     */
    public function getPublishableKey()
    {
        $storeCode = null;
        if(Mage::app()->getStore()->isAdmin()) {
            $storeCode = Mage::getSingleton('adminhtml/session_quote')->getStore()->getCode();
        }
        return Mage::getStoreConfig('payment/pinpay/publishable_key', $storeCode);
    }

    /**
     * Returns the correct service URL depending on whether testing mode is enabled
     *
     * @return string The service URL, or empty string if not defined
     */
    public function getServiceURL()
    {
        $storeCode = null;
        if(Mage::app()->getStore()->isAdmin()) {
            $storeCode = Mage::getSingleton('adminhtml/session_quote')->getStore()->getCode();
        }
        $isTesting = Mage::getStoreConfig('payment/pinpay/test', $storeCode);
        if ($isTesting == true) {
            return Mage::getStoreConfig('payment/pinpay/testing_url', $storeCode);
        } else {
            return Mage::getStoreConfig('payment/pinpay/production_url', $storeCode);
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
        $request = Mage::getModel('pinpay/request');
        $request->setEmail($email)->
            setAmount($request::getAmountInCents($amount))->
            setDescription("Quote #:" . $payment->getOrder()->getRealOrderId())->
            setIpAddress($payment->getAdditionalInformation('ip_address'));

        //Consider card token only if the customer token is not present. 
        if($payment->getAdditionalInformation("customer_token")!=""){
            $request->setCustomerToken($payment->getAdditionalInformation("customer_token"));
        }else{
            $request->setCardToken($payment->getAdditionalInformation("card_token"));
        }


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
     * Build request object for refund api call. Inconsistency in PinPayment API made it difficult to reuse _buildRequest
     * @param $payment
     * @param $amount
     * @return false|Mage_Core_Model_Abstract
     */
    protected function _buildRefundRequest($payment, $amount) {
        $request = Mage::getModel('pinpay/request');
        $request->setAmount($request::getAmountInCents($amount));
        $request->setChargeToken($payment->getCcTransId());
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
     * Process refund through PinPayment online
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }
	/* Rounding error may occur, checking if the differences is not less than 0.5c  */
        if ($amount - $payment->getAmountPaid() - $payment->getAmountRefunded() >= 0.005) {
            Mage::throwException(Mage::helper('payment')->__('Invalid refund amount'));
        }

        /* Check transaction id */
        $creditMemo = Mage::registry('current_creditmemo');
        if (!$creditMemo->getInvoice()->getTransactionId()) {
            Mage::throwException(Mage::helper('payment')->__('Invalid transaction id'));
        }

        /* Refund online through PinPayment gateway*/
        $refundRequest = $this->_buildRefundRequest($payment, $amount);
        $httpResponse = $this->_postRequest($refundRequest, self::REQUEST_TYPE_REFUND);
        $result = Mage::getModel("pinpay/result", $httpResponse);

        switch ($result->getGatewayResponseStatus()) {
            case $result::RESPONSE_CODE_APPROVED:
                // Sets the response token
                /* @var $payment Mage_Sales_Model_Order_Payment */
                $payment->setRefundTransactionId(''. $result->getRefundToken());
                $payment->setAmount($amount);
                if ($result->getRefundToken() != $payment->getParentTransactionId()) {
                    $payment->setTransactionId($result->getRefundToken());
                }
                $shouldCloseCaptureTransaction = $payment->getOrder()->canCreditmemo() ? 0 : 1;
                $payment
                    ->setIsTransactionClosed(1)
                    ->setShouldCloseParentTransaction($shouldCloseCaptureTransaction);
                return $this;
            default:
                Mage::log('Refund could not be processed. ' . $result->getErrorDescription(), Zend_Log::INFO, self::$logFile);
                Mage::throwException((Mage::helper('pinpay')->__($result->getErrorDescription() . self::RESPONSE_APPEND_MSG)));
        }

        return $this;
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

        if($requestType == self::REQUEST_TYPE_AUTH_CAPTURE || $requestType == self::REQUEST_TYPE_AUTH_ONLY) {
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
        } else if ($requestType == self::REQUEST_TYPE_REFUND) {
            $url .= "charges/" . $request->getChargeToken() . "/refunds";
            $client->setMethod($client::POST);
            $client->setParameterPost('amount', $request->getAmount());
        } else if($requestType == self::REQUEST_TYPE_CUSTOMER){
            
            //If the request type is customer, then we will need to send email and card token as post parameters to receive customer token back. 
            $url .= "customers/";
            $customerToken = $request->getData('customer_token');
            
            //If customer token is already present, then we need to update the values using "PUT" method
            //If the customer token is not existing, then we need to "POST" all details to API
            if(isset($customerToken) && $customerToken!=""){
                $url .= $customerToken;
                $client->setMethod($client::PUT);
            }else{
                $client->setMethod($client::POST);
            }

            $client->setParameterPost('email', $request->getData('email'));
            $client->setParameterPost('card_token', $request->getData('card_token'));

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
            'timeout' => Mage::helper('pinpay')->getTimeout()
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
    protected $_canRefund = true;

    /**
     * Can partially refund an invoice?
     */
    protected  $_canRefundInvoicePartial = true;

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
