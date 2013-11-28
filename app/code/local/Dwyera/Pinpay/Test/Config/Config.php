<?php
/**
 * Created by PhpStorm.
 * User: andrew
 * Date: 28/11/13
 * Time: 3:46 PM
 */
class Dwyera_Pinpay_Test_Config_Config extends EcomDev_PHPUnit_Test_Case_Config
{
    /**
     * Test classes are aliased correctly
     *
     * @test
     */
    public function testClassAliases()
    {
        $this->assertModelAlias('pinpay/request', 'Dwyera_Pinpay_Model_Request');
        $this->assertModelAlias('pinpay/paymentMethod', 'Dwyera_Pinpay_Model_PaymentMethod');
    }

    public function testModuleConfig() {
        $this->assertModuleCodePool('local');
        $this->assertModuleDepends('Mage_Payment');

    }
}