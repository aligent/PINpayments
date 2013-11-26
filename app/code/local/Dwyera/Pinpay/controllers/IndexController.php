<?php
class Dwyera_Pinpay_IndexController extends Mage_Core_Controller_Front_Action{
//    public function IndexAction() {
//
//	  $this->loadLayout();
//	  $this->getLayout()->getBlock("head")->setTitle($this->__("Titlename"));
//	        $breadcrumbs = $this->getLayout()->getBlock("breadcrumbs");
//      $breadcrumbs->addCrumb("home", array(
//                "label" => $this->__("Home Page"),
//                "title" => $this->__("Home Page"),
//                "link"  => Mage::getBaseUrl()
//		   ));
//
//      $breadcrumbs->addCrumb("titlename", array(
//                "label" => $this->__("Titlename"),
//                "title" => $this->__("Titlename")
//		   ));
//
//      $this->renderLayout();
//
//    }

    public function TestAction() {

        echo 'test';
        die('test');
        $this->loadLayout();

        $block = $this->getLayout()->createBlock('pinpay/form');
        $this->getLayout()->getBlock('content')->append($block);


        $this->renderLayout();

    }
}