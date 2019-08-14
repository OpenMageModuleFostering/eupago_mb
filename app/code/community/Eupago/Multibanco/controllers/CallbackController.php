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
			//$this->marcaComoPaga($order, $callBack_params->valor); // -> para usar para marcar como paga sem gerar fatura
			$this->capture($order);			
		}	
	}
	
	private function marcaComoPaga($order,$valor_pago){
		
		$order->setData('state', "complete");
		$order->setStatus("processing");
		$order->sendOrderUpdateEmail();
		$history = $order->addStatusHistoryComment('Encomenda paga por MULTIBANCO.', false);
		$history->setIsCustomerNotified(true);
		$order->setTotalPaid($valor_pago);
		$order->save();
		echo "estado alterado para processing com sucesso";
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