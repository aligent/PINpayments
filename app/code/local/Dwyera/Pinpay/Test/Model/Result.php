<?php
/**
 * Created by PhpStorm.
 * User: andrew
 * Date: 29/11/13
 * Time: 11:08 AM
 */
class Dwyera_Pinpay_Test_Model_Result extends EcomDev_PHPUnit_Test_Case
{
    private $model;

    protected function setUp()
    {
        $zendHttpResponse = new Zend_Http_Response(201, array(), '{"response": {}}');
        $this->model = Mage::getModel("pinpay/result", $zendHttpResponse);
        $this->assertInstanceOf('Dwyera_Pinpay_Model_Result', $this->model);
    }

    /**
     * Ensures the result is initialized with the supplied Zend_Http_Response
     *
     * @test
     */
    public function testGetHttpStatus()
    {
        $this->assertEquals(201, $this->model->getHttpStatus());
    }

    /**
     * Checks that the getGatewayResponseStatus function converts the response message into
     * the correct response value
     *
     * @test
     * @dataProvider dataProvider
     * @loadExpectation
     */
    public function testGetGatewayResponseStatus($responseJson, $responseCode, $testRef)
    {

        $zendHttpResponse = new Zend_Http_Response($responseCode, array(), $responseJson);
        $model = Mage::getModel("pinpay/result", $zendHttpResponse);

        $this->assertEquals($this->expected('%s', $testRef)->getCode(), $model->getGatewayResponseStatus());
    }

}