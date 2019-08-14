<?php

class Eupago_Multibanco_Model_Observer
{
    public function pendingPaymentState($observer)
    {
		$order = $observer->getOrder();
		$method = $order->getPayment()->getMethodInstance();
		if ($method->getCode() == 'multibanco')
			$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'A aguardar pagamento por multibanco');
    }
	
	public function sendInvoiceEmail($observer)
	{
		$invoice = $observer->getEvent()->getInvoice();
		$order = $invoice->getOrder();
		$method = $order->getPayment()->getMethodInstance();
		$sendEmail = Mage::getStoreConfig('payment/multibanco/send_invoice_email');
		if ($method->getCode() == 'multibanco' && $sendEmail){
			$invoice->sendEmail();
		}
	}
}