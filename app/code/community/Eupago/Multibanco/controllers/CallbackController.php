<?php
/*
Mygateway Payment Controller
By: Junaid Bhura
www.junaidbhura.com
*/

class Eupago_Multibanco_CallbackController extends Mage_Core_Controller_Front_Action {
	
	public function multibancoAction() {
		
		// tirar de comentário se pretender validar apenas pedidos post
		// if(!$this->getRequest()->isPost()) 
			// exit("pedido de callback deve ser post");
		
		// carrega dados de callback e encomenda
		$callBack_params = (object)$this->getRequest()->getParams();
		$order = Mage::getModel('sales/order')->load($callBack_params->identificador, 'increment_id');		
		
		// valida metodo de pagamento
		if(!isset($callBack_params->mp) || $callBack_params->mp != 'PC:PT')
			exit("método de pagamento inválido");
		
		// valida chave API
		if($callBack_params->chave_api != Mage::getStoreConfig('payment/multibanco/chave'))
			exit("chave API inválida");
		
		// valida order_id
		if($order->getId() == null)
			exit("a encomenda não existe");

		// valida estado da encomenda
		if($order->getStatus() == "canceled") // devemos validar se esta completa?
			exit("não foi possivel concluir o pagamento porque o estado da encomenda é: ".$order_status);
		
		// valida valor da encomenda -> comentar no caso de premitir pagamento parcial
		if($order->getGrandTotal() != $callBack_params->valor)
			exit ("O valor da encomenda e o valor pago não correspondem!");
		
		// verifica se a encomenda já está paga
		if($order->getBaseTotalDue() == 0)
			exit("A encomenda já se encontra paga!");
		
		// valida valor por pagar
		if($order->getBaseTotalDue() < $callBack_params->valor)
			exit("O valor a pagamento é inferior ao valor pago!");
		
		// marca como paga ou gera fatura
		if($this->validaTransacao($callBack_params, $order)){
			if(class_exists('SOAPClient'))
				$this->capture($order);	
			else
				$this->marcaComoPaga($order, $callBack_params->valor, true); // -> para usar para marcar como paga sem gerar fatura		
		}	
	}
	
	// CALLBACK ANTIGO FICARÁ DEPRECATED NA PROXIMA VERSÃO
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
		
		// se não existir dados de pagamento antigos chama o novo callback
		if($pagamento->eupago_referencia == null || $pagamento->eupago_referencia == "" ){
			$this->multibancoAction();
			return;
		}
			
		/////// dados do pagamento antigos
		$entidade = $pagamento->eupago_entidade;
		$referencia = str_pad($pagamento->eupago_referencia, 9, "0", STR_PAD_LEFT);
		$valor_gerado = $pagamento->eupago_montante;
		
		/////// gera autorizacao
		$chave_api = Mage::getStoreConfig('payment/multibanco/chave');
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

				//////// hack for generate invoice automatically
				$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
				if (!$invoice->getTotalQty()) {
					Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
				}
				$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
				$invoice->register();
				$transactionSave = Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder());
				$transactionSave->save();
				////////////////
				
			$history = $order->addStatusHistoryComment('Order marked as complete automatically.', false);
			$history->setIsCustomerNotified(true);
			$order->save();
			
			echo "alterado para pago..";
		}else{
			echo "os valores de pagamento não correspondem com os da encomenda";
		}
	}
	
	private function marcaComoPaga($order,$valor_pago,$geraFatura = false){
		$order->setData('state', "complete");
		$order->setStatus("processing");
		$order->sendOrderUpdateEmail();
		$history = $order->addStatusHistoryComment('Encomenda paga por MULTIBANCO.', false);
		$history->setIsCustomerNotified(true);
		if($geraFatura)
			$this->geraFatura($order);
		$order->setTotalPaid($valor_pago);
		$order->save();
		echo "estado alterado para processing com sucesso. e gerada fatura sem transação";
	}
	
	private function geraFatura($order){
		//////// hack para gerar fatura sem transação automaticamente
		$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
		if (!$invoice->getTotalQty()) {
			Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
		}
		$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
		$invoice->register();
		$transactionSave = Mage::getModel('core/resource_transaction')
			->addObject($invoice)
			->addObject($invoice->getOrder());
		$transactionSave->save();
	}
	
	private function validaTransacao($CallBack, $order){
			
		/////// dados do pagamento
		$payment = $order->getPayment();
		
		///// dados transaction
		$transaction = $payment->getTransaction(intval($CallBack->referencia));
		if($transaction == false){
			echo "a referencia não corresponde a nenhuma transação desta encomenda.";
			return false;
		}
		
		return true;
	}
	
	// gera invoice
	private function capture($order){	
		$payment = $order->getPayment();
		$payment->capture();
		$order->save();
		echo "Pagamento foi capturado com sucesso. e a fatura foi gerada";
	}
	
}