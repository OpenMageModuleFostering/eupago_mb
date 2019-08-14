<?php
class Eupago_Multibanco_Block_Success extends Mage_Checkout_Block_Onepage_Success
{
    protected function _construct()
    {
		echo "passou aki";
        parent::_construct();
        $this->setTemplate('multibanco/checkout/success.phtml');
    }

}

?>