<?php
/**
 * Created by PhpStorm.
 * User: andrew
 * Date: 28/11/13
 * Time: 10:38 AM
 */

class Dwyera_Pinpay_Test_Model_PaymentMethod extends EcomDev_PHPUnit_Test_Case
{

    /** @var  $model Dwyera_Pinpay_Model_PaymentMethod */
    protected $model;

    private $skipPostParamCheck = false;

    private $testNumber;

    private $orderNumber;

    /**
     * Confirms that the secret key is returned correctly from the DB
     *
     * dataProvider dataProvider
     * @loadFixture fieldsets.yaml
     * @loadExpectation fieldsets.yaml
     * @test
     */
    public function getSecretKey()
    {
        $sKey = $this->model->getSecretKey();
        $this->assertEquals($this->expected('keys')->getSecretKey(), $sKey);
    }

    /**
     * Confirms that the publishable key is returned correctly from the DB
     *
     * @loadFixture fieldsets.yaml
     * @loadExpectation fieldsets.yaml
     * @test
     */
    public function getPublishableKey()
    {
        $pKey = $this->model->getPublishableKey();
        $this->assertEquals($this->expected('keys')->getPublishableKey(), $pKey);
    }

    /**
     * Test to confirm that getSecretKey and getPublishableKey return
     * an empty string when the key is empty
     * @loadFixture empty_fieldsets.yaml
     * @loadExpectation fieldsets.yaml
     * @test
     */
    public function getKeyMissing()
    {
        $this->assertEquals($this->expected('empty')->getEmpty(), $this->model->getPublishableKey());
        $this->assertEquals($this->expected('empty')->getEmpty(), $this->model->getSecretKey());
    }

    /**
     * Tests whether the correct service URL is returned for test mode
     *
     * @loadFixture fieldsets_testing.yaml
     * @loadExpectation fieldsets.yaml
     * @test
     */
    public function getServiceURLTestMode()
    {
        $this->assertEquals($this->expected('urls')->getTestingUrl(), $this->model->getServiceURL());
    }

    /**
     * Tests whether the correct service URL is returned for production mode
     *
     * @loadFixture fieldsets.yaml
     * @loadExpectation fieldsets.yaml
     * @test
     */
    public function getServiceURLProductionMode()
    {
        $this->assertEquals($this->expected('urls')->getProductionUrl(), $this->model->getServiceURL());
    }

    protected function setUp()
    {
        parent::setUp();

        $this->model = Mage::getModel("pinpay/paymentMethod");

        $this->assertInstanceOf('Dwyera_Pinpay_Model_PaymentMethod', $this->model);

        $this->_mockSessionCookie('customer/session');
        $this->_mockSessionCookie('admin/session');
        $this->_mockSessionCookie('adminhtml/session');
        $this->_mockSessionCookie('core/session');
        $this->_mockSessionCookie('checkout/session');

        Mage::unregister('_singleton/eav/config');
    }


    /**
     * Mock session storage because sessions aren't available inside phpUnit.
     *
     * @param $vSessionName string Session model alias
     */
    protected function _mockSessionCookie($vSessionName){
        $sessionMock = $this->getModelMock($vSessionName, array('init'));
        $sessionMock->expects($this->any())
            ->method('init')
            ->will($this->returnSelf());

        $this->replaceByMock('singleton', $vSessionName, $sessionMock);
        $this->replaceByMock('model', $vSessionName, $sessionMock);
    }

    /**
     * Test that calls to the authorize method return the correct results
     * @test
     * @loadFixture orders
     * @dataProvider dataProvider
     * @loadExpectation
     */
    public function testSuccessAuthorize($orderNum, $orderVal, $testNumber, $responseValue, $responseCode)
    {
        $this->testNumber = $testNumber;
        $this->orderNumber = $orderNum;

        $paymentMock = $this->setupAuthorizeMocks($responseValue, $responseCode);
        $orderPayment = $this->setupOrder($orderNum, $paymentMock);

        $resVal = $paymentMock->authorize($orderPayment, $orderVal);
        // the authorize method should return a copy of itself
        $this->assertEquals($paymentMock, $resVal);

        $responseObj = json_decode($responseValue);
        $this->checkPaymentProperties($orderPayment, null, $responseObj->response->token);
    }

    /**
     * Test that calls to the authorize method with fraudulent cards are recorded correctly
     * @test
     * @loadFixture orders
     * @dataProvider dataProvider
     */
    public function testRecordFraudAuthorize($orderNum, $orderVal, $responseValue, $responseCode)
    {
        $this->skipPostParamCheck = true;
        $paymentMock = $this->setupAuthorizeMocks($responseValue, $responseCode);
        $orderPayment = $this->setupOrder($orderNum, $paymentMock);

        $resVal = $paymentMock->authorize($orderPayment, $orderVal);
        // the authorize method should return a copy of itself
        $this->assertEquals($paymentMock, $resVal);

        $responseObj = json_decode($responseValue);
        $this->checkPaymentProperties($orderPayment, true, $responseObj->charge_token);
    }

    /**
     * Test that calls to the authorize method with incorrect payment details fail with an exception
     * @test
     * @loadFixture orders
     * @dataProvider dataProvider
     * @expectedException Mage_Core_Exception
     */
    public function testFailureAuthorize($orderNum, $orderVal, $responseValue, $responseCode)
    {
        $this->orderNumber = $orderNum;

        // Don't check the parameters sent to the _postParams method
        $this->skipPostParamCheck = true;

        $paymentMock = $this->setupAuthorizeMocks($responseValue, $responseCode);
        $orderPayment = $this->setupOrder($orderNum, $paymentMock);

        $paymentMock->authorize($orderPayment, $orderVal);
    }

    /**
     * Test that calls to the authorize with a negative amount throw an exception
     * @test
     * @loadFixture orders
     * @expectedException Mage_Core_Exception
     * @dataProvider dataProvider
     */
    public function testNegativeAmountAuthorize($responseStatus, $orderNum, $orderVal)
    {
        $orderPayment = $this->setupOrder($orderNum);
        Mage::getModel('pinpay/paymentMethod')->authorize($orderPayment, $orderVal);
    }

    private function setupOrder($orderNum, $paymentMethodMock = null)
    {
        $order = Mage::getModel('sales/order')->load($orderNum);
        $orderPayment = Mage::getModel('sales/order_payment')->load($orderNum);
        $orderPayment->setOrder($order);

        $quote = Mage::getModel('sales/quote')->load($orderNum);
        $orderPayment->setQuote($quote);

        if($paymentMethodMock) {
            $paymentMethodMock->setInfoInstance($orderPayment);
        }
        return $orderPayment;
    }

    /**
     * Ensures correct payment properties are set
     * @param $orderPayment
     * @param $expectedFraudValue
     * @param $expectedTransactionId
     */
    private function checkPaymentProperties($orderPayment, $expectedFraudValue, $expectedTransactionId)
    {
        $this->assertEquals($expectedFraudValue, $orderPayment->getIsTransactionPending());
        $this->assertEquals($expectedFraudValue, $orderPayment->getIsFraudDetected());
        $this->assertEquals($expectedTransactionId, $orderPayment->getCcTransId());
        $this->assertEquals($expectedTransactionId, $orderPayment->getTransactionId());
    }

    private function setupAuthorizeMocks($responseValue, $responseCode)
    {
        /*
         * Mock the paymentMethod model to apply a partial mock.
         * This overrides the _postRequest method to stop it from
         * sending a request to the server.
         */
        $zendHttpResponse = new Zend_Http_Response($responseCode, array(), $responseValue);
        $paymentMock = $this->getModelMock('pinpay/paymentMethod', array('_postRequest'));

        $paymentMock->expects($this->once())->method('_postRequest')
            ->with($this->callback(array($this, 'checkPostParam')),
                $this->anything())
            ->will($this->returnValue($zendHttpResponse));

        return $paymentMock;
    }

    public function checkPostParam($paymentRequest)
    {
        // skip validation if requested.
        if ($this->skipPostParamCheck) {
            return true;
        }
        // Check that the supplied array is the same as the one in the expectation
        return $this->expected('%s-%s', $this->orderNumber, $this->testNumber)->getData() == $paymentRequest->getData();

    }



}