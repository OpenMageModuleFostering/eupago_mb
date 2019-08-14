<?php
class Eupago_Multibanco_Block_Form extends Mage_Payment_Block_Form
{
	protected function _construct()
    {
		$mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('multibanco/form/mark.phtml');
		
        $this->setTemplate('multibanco/form/form.phtml')
			 ->setMethodLabelAfterHtml($mark->toHtml())
		;
		parent::_construct();
    }
}