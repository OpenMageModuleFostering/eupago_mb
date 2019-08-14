<?php

class Eupago_Multibanco_CallbackController extends Mage_Core_Controller_Front_Action {
	
	// valida todos os metodos de pagamento
	public function allAction(){
		
		// tirar de comentário se pretender validar apenas pedidos post
		// if(!$this->getRequest()->isPost()) 
			// exit("pedido de callback deve ser post");
		
		// carrega dados de callback e encomenda
		$callBack_params = (object)$this->getRequest()->getParams();
		$order = Mage::getModel('sales/order')->load($callBack_params->identificador, 'increment_id');		
		$method = $order->getPayment()->getMethod();
		$metodo_callback = null;
		switch(urldecode($callBack_params->mp)){
			case 'PC:PT':
				$metodo_callback = "multibanco";
				break;
			case 'MW:PT':
				$metodo_callback = "mbway";
				break;
			default:
				exit("método de pagamento inválido");
		}
		
		// devolvemos forbidden por defeito
		http_response_code(403);
			
		// valida metodo de pagamento
		if(!isset($callBack_params->mp) || $method != $metodo_callback)
			exit("método de pagamento não corresponde ao da encomenda");
		
		// valida chave API
		if($callBack_params->chave_api != Mage::getStoreConfig('payment/'.$metodo_callback.'/chave'))
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
			http_response_code(200);
			if(class_exists('SOAPClient'))
				$this->capture($order);	
			else
				$this->marcaComoPaga($order, $callBack_params->valor, true); // -> para usar para marcar como paga sem gerar fatura			
		}
	}
	
	public function multibancoAction($params = null) {
		
		// tirar de comentário se pretender validar apenas pedidos post
		// if(!$this->getRequest()->isPost()) 
			// exit("pedido de callback deve ser post");
		
		// carrega dados de callback e encomenda
		$callBack_params = ($params == null) ? (object)$this->getRequest()->getParams() : $params;
		$order = Mage::getModel('sales/order')->load($callBack_params->identificador, 'increment_id');		
		
		// valida metodo de pagamento
		if(!isset($callBack_params->mp) || urldecode($callBack_params->mp) != 'PC:PT')
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
	
	public function mbwayAction($params = null) {
		
		// tirar de comentário se pretender validar apenas pedidos post
		// if(!$this->getRequest()->isPost()) 
			// exit("pedido de callback deve ser post");
		
		// carrega dados de callback e encomenda
		$callBack_params = ($params == null) ? (object)$this->getRequest()->getParams() : $params;
		$order = Mage::getModel('sales/order')->load($callBack_params->identificador, 'increment_id');		
		
		// valida metodo de pagamento
		if(!isset($callBack_params->mp) || urldecode($callBack_params->mp) != 'MW:PT')
			exit("método de pagamento inválido");
		
		// valida chave API
		if($callBack_params->chave_api != Mage::getStoreConfig('payment/mbway/chave'))
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