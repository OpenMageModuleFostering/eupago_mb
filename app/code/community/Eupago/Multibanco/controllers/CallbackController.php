<?php

class Eupago_Multibanco_CallbackController extends Mage_Core_Controller_Front_Action { // extends Mage_Payment_Model_Method_Abstract 
	
	public function autorizeAction(){
		
		///// dados vindos da api para comfirmar 
		$CallBack = $this->getRequest()->getParams();
		$CallBack_valor = $CallBack['valor'];
		$CallBack_referencia = $CallBack['referencia'];
		$CallBack_chave_api = $CallBack['chave_api'];
		$CallBack_orderId = $CallBack['identificador'];
		$CallBack_autorizacao = $CallBack['autorizacao'];

		
		////// dados de encomenda
		$OrderNumber = $CallBack_orderId; //$CallBack_orderId vaem da api Eupago[order-id]
		$order = Mage::getModel('sales/order')->load($OrderNumber, 'increment_id');
		$valor_encomenda = $order->grand_total; //retirado do valor total da encomenda
		
		/////// dados do pagamento
		$pagamento = $order->getPayment();
		$entidade = $pagamento->eupago_entidade;
		$referencia = $pagamento->eupago_referencia;
		$valor_gerado = $pagamento->eupago_montante;
		
		/////// gera autorizacao
		$chave_api = Mage::getModel('multibanco/process')->getConfigData('chave');
		$autorizacao = md5(date('Y-m-d').$chave_api);
		
		//////// Confere dados
		$confere_montantes = (($valor_encomenda == $valor_gerado) == $CallBack_valor ? true : false);
		$confere_autorizacao = ($autorizacao == $CallBack_autorizacao ? true : false);
		$confere_referencia = ($referencia == $CallBack_referencia ? true : false);
		$confere_chave_api = ($CallBack_chave_api == $chave_api ? true : false);
		
		////// se tudo ok, faz o update do estado da encomenda e envia um email ao cliente
		if($confere_montantes && $confere_chave_api && $confere_referencia){ /*futuro upgrade -> $confere_autorizacao*/
			$order->setData('state', "complete");
			$order->setStatus("processing");
			$order->sendOrderUpdateEmail();
			$history = $order->addStatusHistoryComment('Order marked as complete automatically.', false);
			$history->setIsCustomerNotified(true);
			$order->save();
		}
	}
}
