<?php
/**
 * Created by PhpStorm.
 * User: andrew
 * Date: 28/11/13
 * Time: 10:38 AM
 */

class Dwyera_Pinpay_Test_Model_PaymentMethod extends EcomDev_PHPUnit_Test_Case {

    /** @var  $model Dwyera_Pinpay_Model_PaymentMethod */
    protected $model;

    protected function setUp() {

        $this->model = Mage::getModel("pinpay/paymentMethod");

        $this->assertInstanceOf('Dwyera_Pinpay_Model_PaymentMethod', $this->model);
    }

    /**
     * Confirms that the secret key is returned correctly from the DB
     *
     * @test
     */
    public function testGetSecretKey() {
        $sKey = $this->model->getSecretKey();
        $this->assertNotEmpty($sKey);
    }


}