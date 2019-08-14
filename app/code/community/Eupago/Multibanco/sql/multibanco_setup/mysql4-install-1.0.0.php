<?php
$installer = $this;

$installer->startSetup();
$installer->addAttribute('order_payment', 'eupago_entidade', array('type'=>'varchar'));
$installer->addAttribute('order_payment', 'eupago_referencia', array('type'=>'varchar'));
$installer->addAttribute('order_payment', 'eupago_montante', array('type'=>'varchar'));

$installer->addAttribute('quote_payment', 'eupago_entidade', array('type'=>'varchar'));
$installer->addAttribute('quote_payment', 'eupago_referencia', array('type'=>'varchar'));
$installer->addAttribute('quote_payment', 'eupago_montante', array('type'=>'varchar'));
$installer->endSetup();

if (Mage::getVersion() >= 1.1) {
    $installer->startSetup();    
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'eupago_entidade', 'VARCHAR(255) NOT NULL');
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'eupago_referencia', 'VARCHAR(255) NOT NULL');
	$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote_payment'), 'eupago_montante', 'VARCHAR(255) NOT NULL');
    $installer->endSetup();
}