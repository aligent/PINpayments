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

    private $pinpaymentsResultMock;

   /* protected function setUp()
    {


    }*/

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
    public function getServiceURLTestMode() {
        $this->assertEquals($this->expected('urls')->getTestingUrl(), $this->model->getServiceURL());
    }

    /**
     * Tests whether the correct service URL is returned for production mode
     *
     * @loadFixture fieldsets.yaml
     * @loadExpectation fieldsets.yaml
     * @test
     */
    public function getServiceURLProductionMode() {
        $this->assertEquals($this->expected('urls')->getProductionUrl(), $this->model->getServiceURL());
    }

    protected function setUp()
    {
        parent::setUp();

        $this->model = Mage::getModel("pinpay/paymentMethod");

        $this->assertInstanceOf('Dwyera_Pinpay_Model_PaymentMethod', $this->model);
    }

    /**
     * Authorize integration tests.
     * @test
     * @loadFixture orders
     * @dataProvider dataProvider
     */
    public function testSuccessAuthorize($responseStatus, $orderNum, $orderVal) {

        $paymentMock = $this->setupAuthorizeMocks($responseStatus, $orderNum);
        $orderPayment = $this->setupOrder($orderNum);

        $resVal = $paymentMock->authorize($orderPayment, $orderVal);
        // the authorize method should return a copy of itself
        $this->assertEquals($paymentMock, $resVal);
    }

    private function setupOrder($orderNum) {
        $order = Mage::getModel('sales/order')->load($orderNum);
        $orderPayment = Mage::getModel('sales/order_payment')->load($orderNum);
        $orderPayment->setOrder($order);
        return $orderPayment;
    }

    private function setupAuthorizeMocks($responseStatus) {
        // mock pinpay response instance
        $this->pinpaymentsResultMock = $this->getModelMock('pinpay/result',
            array('getGatewayResponseStatus', 'getResponseToken'),
            false, array(null), "",  false);

        $this->pinpaymentsResultMock->expects($this->any())
            ->method('getGatewayResponseStatus')
            ->will($this->returnValue($responseStatus));
        $this->pinpaymentsResultMock->expects($this->any())
            ->method('getResponseToken')
            ->will($this->returnValue('1'));
        $this->replaceByMock('model', 'pinpay/result', $this->pinpaymentsResultMock);

        /*
         * Mock the paymentMethod model to apply a partial mock.
         * This overrides the _postRequest method to stop it from
         * sending a request to the server.
         */
        $paymentMock = $this->getModelMock('pinpay/paymentMethod', array('_postRequest'));
        $paymentMock->expects($this->once())->method('_postRequest')
            ->will($this->returnValue(null));

        return $paymentMock;
    }

}