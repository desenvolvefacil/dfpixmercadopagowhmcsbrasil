<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

// Detect module name from filename.
define('PAYMENT_METHOD_MP_PIX', 'dfpixmercadopago');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables(PAYMENT_METHOD_MP_PIX);
$access_token = trim($gatewayParams["AccessTokenProducao"]);


$input = file_get_contents("php://input");
$notification = json_decode($input, true);


logTransaction(PAYMENT_METHOD_MP_PIX, json_encode($notification), "Notificação Recebida");

//caso seja uma notificação de pagamento
if (isset($notification["data"]["id"]) && $notification["type"] == "payment") {
    $payment_id = $notification["data"]["id"];
    
    // Consulta detalhes do pagamento na API
    $url = "https://api.mercadopago.com/v1/payments/" . $payment_id;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $access_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $payment = json_decode($response, true);
    
    logTransaction(PAYMENT_METHOD_MP_PIX, $payment, "Retorno Recebido");
    
    if (isset($payment["status"])) {
        $status = $payment["status"]; // pending, approved, rejected...
        $external_reference = $payment["external_reference"];
        
        $idfatura = str_ireplace("DF", "", $external_reference);
        $idfatura = intval($idfatura);
        
        //logTransaction(PAYMENT_METHOD_MP_PIX, $idfatura, "ID FATURA");
        
        if ($status == "approved") {
            
            //Verifica se a fatura já foi marcada como paga manualmente
            $invoice = Capsule::table('tblinvoices')
            ->where('id', $idfatura)
            ->first();
            
            
            logTransaction(PAYMENT_METHOD_MP_PIX, $invoice, "ResultadoBD");
            
            if (!$invoice) {
                logTransaction(PAYMENT_METHOD_MP_PIX, json_encode($log), $idfatura. " Fatura não Encontrada");
            }else{
                if(strtolower($invoice->status) == 'paid'){
                    logTransaction(PAYMENT_METHOD_MP_PIX, json_encode($log), $idfatura." Fatura já foi Marca como Paga");
                }else{
                        $paymentFee = floatval($payment["fee_details"][0]["amount"]);
                    
                        $paymentAmount = $payment["transaction_amount"];

                        $log = [];
                        $log["IdFatura"] = $idfatura;
                        $log["RetornoMP"] = $payment;
                        
                        //$transactionStatus = 'Success';
                        logTransaction(PAYMENT_METHOD_MP_PIX, json_encode($log), "Pix Pago #".$idfatura);
                        
                        addInvoicePayment(
                                $idfatura,
                                $payment_id,
                                $paymentAmount,
                                $paymentFee,
                                PAYMENT_METHOD_MP_PIX
                            );
                }
        
        }
        
        }
    }
    
    http_response_code(200);
}
