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

    protected function setUp()
    {

        $this->model = Mage::getModel("pinpay/paymentMethod");

        $this->assertInstanceOf('Dwyera_Pinpay_Model_PaymentMethod', $this->model);
    }

    /**
     * Confirms that the secret key is returned correctly from the DB
     *
     * dataProvider dataProvider
     * @loadFixture fieldsets.yaml
     * @loadExpectation keys.yaml
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
     * @loadExpectation keys.yaml
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
     * @loadExpectation keys.yaml
     * @test
     */
    public function getKeyMissing()
    {
        $this->assertEquals($this->expected('empty')->getEmpty(), $this->model->getPublishableKey());
        $this->assertEquals($this->expected('empty')->getEmpty(), $this->model->getSecretKey());

    }


}