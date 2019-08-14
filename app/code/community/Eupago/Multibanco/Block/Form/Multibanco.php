<?php
class Eupago_Multibanco_Block_Form_Multibanco extends Mage_Payment_Block_Form
{
    protected function _construct()
    {		
		if(Mage::getStoreConfig('payment/multibanco/mostra_icon'))
			$this->setMethodLabelAfterHtml('<img style="padding:0 5px;"src="'.$this->getSkinUrl('images/eupago/multibanco/multibanco_icon.png').'" />');
    }
}