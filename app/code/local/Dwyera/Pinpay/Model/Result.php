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

    const RESPONSE_CODE_APPROVED = 1;
    const RESPONSE_CODE_DECLINED = 2;
    const RESPONSE_CODE_INSUF_FUNDS = 3;
    const RESPONSE_CODE_PROC_ERROR = 4;
    const RESPONSE_CODE_SUSP_FRAUD = 5;
    const RESPONSE_CODE_CARD_EXPIRED = 6;

    private $response;
    private $msgObj;

    /**
     *
     *
     * @param Zend_Http_Response $response JSON response from the PinPayments gateway
     * @throws Dwyera_Pinpay_Model_ResponseParseException If an invalid JSON response object is passed
     */
    public function __construct(Zend_Http_Response $response)
    {
        $this->response = $response;
        $this->msgObj = json_decode($this->response->getBody());
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
        if (isset($this->msgObj->response) && $this->msgObj->response->success) {
            return self::RESPONSE_CODE_APPROVED;
        } else {
            return self::RESPONSE_CODE_DECLINED;
        }
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

    public function getErrorDescription()
    {
        if (isset($this->msgObj->response) && $this->msgObj->response->success) {
            return "";
        } elseif (isset($this->msgObj->error_description)) {
            return $this->msgObj->error_description;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }

    public function getErrorMessages()
    {
        if (isset($this->msgObj->response) && $this->msgObj->response->success) {
            return array();
        } elseif (isset($this->msgObj->messages)) {
            return $this->msgObj->messages;
        } else {
            throw new Dwyera_Pinpay_Model_ResponseParseException;
        }
    }


}