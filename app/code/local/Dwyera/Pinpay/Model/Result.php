<?php
/**
 * Wrapper for Zend_Http_Response to extract PinPayments gateway response details
 * Note that this class should only be used to wrap a valid response from the PinPayments gateway
 * User: andrew
 * Date: 29/11/13
 * Time: 11:03 AM
 */
class Dwyera_Pinpay_Model_Result extends Varien_Object
{


    const HTTP_RESPONSE_CODE_APPROVED = 201;
    const HTTP_RESPONSE_CODE_INVALID = 422;
    const HTTP_RESPONSE_CODE_FAILED = 400;

    const RESPONSE_CODE_APPROVED = 1;
    const RESPONSE_CODE_DECLINED = 2;
    const RESPONSE_CODE_INSUF_FUNDS = 3;
    const RESPONSE_CODE_PROC_ERROR = 4;
    const RESPONSE_CODE_SUSP_FRAUD = 5;
    const RESPONSE_CODE_CARD_EXPIRED = 6;
    const RESPONSE_CODE_INVALID_RESOURCE = 7;

    private $response;
    private $msgObj;
    private $httpResponseCode;

    /**
     *
     *
     * @param Zend_Http_Response $response JSON response from the PinPayments gateway
     * @throws Dwyera_Pinpay_Model_ResponseParseException If an invalid JSON response object is passed
     */
    public function __construct(Zend_Http_Response $response)
    {
        $this->response = $response;
        $this->httpResponseCode = $response->getStatus();
        $this->msgObj = json_decode($response->getBody());
        if ($this->msgObj == null) {
            throw new Dwyera_Pinpay_Model_ResponseParseException("Could not parse PinPayments gateway response");
        }
    }

    public function getHttpStatus()
    {
        return $this->response->getStatus();
    }

    /**
     * Returns a response type from the response returned from the PinPayments gateway
     *
     * @throws Dwyera_Pinpay_Model_ResponseParseException
     * @return int
     */
    public function getGatewayResponseStatus()
    {
        if (!isset($this->msgObj)) {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }

        $responseVal = self::RESPONSE_CODE_DECLINED;

        if ($this->httpResponseCode == self::HTTP_RESPONSE_CODE_APPROVED) {
            $responseVal = self::RESPONSE_CODE_APPROVED;
        } elseif ($this->httpResponseCode == self::HTTP_RESPONSE_CODE_INVALID) {
            $responseVal = self::RESPONSE_CODE_INVALID_RESOURCE;
        } elseif ($this->httpResponseCode == self::HTTP_RESPONSE_CODE_FAILED && isset($this->msgObj->error)) {
            switch($this->msgObj->error) {
                case 'card_declined':
                    $responseVal = self::RESPONSE_CODE_DECLINED;
                    break;
                case 'insufficient_funds':
                    $responseVal = self::RESPONSE_CODE_INSUF_FUNDS;
                    break;
                case 'processing_error':
                    $responseVal = self::RESPONSE_CODE_PROC_ERROR;
                    break;
                case 'suspected_fraud':
                    $responseVal = self::RESPONSE_CODE_SUSP_FRAUD;
                    break;
                case 'expired_card':
                    $responseVal = self::RESPONSE_CODE_CARD_EXPIRED;
                    break;
                default:
                     $responseVal = self::RESPONSE_CODE_DECLINED;
            }
        }

        return $responseVal;
    }

    public function getResponseCodeDescription($responseCode)
    {
        switch ($responseCode) {
            case self::RESPONSE_CODE_APPROVED:
                return "Payment Approved";
            case self::RESPONSE_CODE_DECLINED:
                return "Payment Denied";
            case self::RESPONSE_CODE_INSUF_FUNDS:
                return "Insufficient Funds";
            case self::RESPONSE_CODE_PROC_ERROR:
                return "Processing Error";
            case self::RESPONSE_CODE_SUSP_FRAUD:
                return "Suspected Fraud";
            case self::RESPONSE_CODE_CARD_EXPIRED:
                return "Card Expired";
            default:
                throw new InvalidArgumentException("Unknown response code: $responseCode");
        }
    }

    /**
     * Gets the complete error description.  In the case of multiple error messages, these will be concatenated
     * @return string
     * @throws Dwyera_Pinpay_Model_ResponseParseException
     */
    public function getErrorDescription()
    {
        if ($this->isSuccessResponse()) {
            return "";
        } elseif (isset($this->msgObj->error_description)) {
            $errorMsg = $this->msgObj->error_description;
            if($this->msgObj->error == "invalid_resource") {
                $errorMsg.=" ( ";
                $messages = $this->getErrorMessages();
                foreach($messages as $message) {
                    $errorMsg .= $message->message.". ";
                }
                $errorMsg.=")";
            }
            return $errorMsg;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    public function getErrorMessages()
    {
        if ($this->isSuccessResponse()) {
            return array();
        } elseif (isset($this->msgObj->messages)) {
            return $this->msgObj->messages;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    public function getErrorToken()
    {
        if ($this->isSuccessResponse()) {
            return "";
        } elseif (isset($this->msgObj->charge_token)) {
            return $this->msgObj->charge_token;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    public function getResponseToken()
    {
        if ($this->isSuccessResponse()) {
            return $this->msgObj->response->token;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }
    public function getCustomerToken()
    {
        if (isset($this->msgObj->response->token)) {
            return $this->msgObj->response->token;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    public function getPrimaryCardDisplayNumber()
    {
        if (isset($this->msgObj->response->card->display_number)) {
            return $this->msgObj->response->card->display_number;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    public function getCardToken()
    {
        if (isset($this->msgObj->response->card->token)) {
            return $this->msgObj->response->card->token;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    /**
     * Similar to get getResponseToken but skip check success response
     * (Inconsistency in PIN PAYMENT API)
     */
    public function getRefundToken() {
        return $this->msgObj->response->token;
    }

    public function isSuccessResponse()
    {
        return isset($this->msgObj->response) && $this->msgObj->response->success;
    }


}