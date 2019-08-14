<?php
class Eupago_Multibanco_Model_System_Config_Template
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
			array('value' => 'multibanco', 'label' => Mage::helper('adminhtml')->__('Multibanco')),
			array('value' => 'nenhum', 'label' => Mage::helper('adminhtml')->__('Nenhum')),
		);
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
			'multibanco' 	=> Mage::helper('adminhtml')->__('Multibanco'),
			'nenhum' => Mage::helper('adminhtml')->__('Nenhum'),
		);
    }
}