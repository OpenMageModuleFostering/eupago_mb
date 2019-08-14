<?php
class Eupago_Multibanco_Block_Onepage_Success extends Mage_Checkout_Block_Onepage_Success
{
	protected function _construct()
    {
       parent::_construct();
       //$this->setTemplate('eupago/multibanco/checkout/success.phtml');
    }
	
	public function getMultibancoData(){
		$order = $order = Mage::getModel('sales/order')->loadByIncrementId($this->getOrderId());
		
		// vai á base de dados buscar todas as transaction desta encomenda
		$collection = Mage::getModel('sales/order_payment_transaction')
                  ->getCollection()
                  ->addAttributeToFilter('order_id', array('eq' => $order->getEntityId()))
                  ->addAttributeToFilter('txn_type', array('eq' => 'order'))
                  ->addPaymentIdFilter($order->getPayment()->getId());
		
		// retorna os valores da ultima transação
		foreach($collection as $transaction){
			   $rawValue = $transaction->getAdditionalInformation();
			   if($rawValue['raw_details_info']['method'] == 'MULTIBANCO' && is_numeric($rawValue['raw_details_info']['referencia'])){
					$entidade = $rawValue['raw_details_info']['entidade'];
					$referencia = $rawValue['raw_details_info']['referencia'];   
			   }
		}

		return (object)array("entidade" => $entidade, "referencia" => $referencia, "valor" => $order->getGrandTotal());
	}

	
}