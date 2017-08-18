<?php
/** @var Mage_Core_Model_Resource_Setup $installer */

$installer = new Mage_Customer_Model_Entity_Setup('core_setup');

$installer->startSetup();


/* @var $eavConfig Mage_Eav_Model_Config */
$installer->addAttribute('customer', 'pinpayment_customer_token', array(
    'type'      => 'varchar',
    'label'     => 'Pinpayment customer token',
    'input'     => 'text',
    'visible'   => 0,
    'required'  => 0,
    'position'  => 1,
    'required'  => false,
    'default'   => null,
    'user_defined' => true
));

/* @var $eavConfig Mage_Eav_Model_Config */
$installer->addAttribute('customer', 'pinpayment_card_display_number', array(
    'type'      => 'varchar',
    'label'     => 'Pinpayment customer card display number',
    'input'     => 'text',
    'visible'   => 0,
    'required'  => 0,
    'position'  => 1,
    'required'  => false,
    'default'   => null,
    'user_defined' => true
));
$installer->endSetup();

/* @var $eavConfig Mage_Eav_Model_Config */
$installer->addAttribute('customer', 'pinpayment_card_token', array(
    'type'      => 'varchar',
    'label'     => 'Pinpayment customer card token associated with customer',
    'input'     => 'text',
    'visible'   => 0,
    'required'  => 0,
    'position'  => 1,
    'required'  => false,
    'default'   => null,
    'user_defined' => true
));
$installer->endSetup();