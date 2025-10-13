<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

//use WHMCS\Database\Capsule;

// Detect module name from filename.
define('PAYMENT_METHOD', 'dfpixmercadopago');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables(PAYMENT_METHOD);
$access_token = trim($gatewayParams["AccessTokenProducao"]);


$input = file_get_contents("php://input");
$notification = json_decode($input, true);

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
    
    if (isset($payment["status"])) {
        $status = $payment["status"]; // pending, approved, rejected...
        $external_reference = $payment["external_reference"];
        
        $idfatura = str_ireplace("DF", "", $external_reference);
        $idfatura = intval($idfatura);
        
        if ($status == "approved") {

            $paymentFee = floatval($payment["fee_details"][0]["amount"]);
        
            $paymentAmount = $payment["transaction_amount"];
        
        
        $log = [];
        $log["IdFatura"] = $idfatura;
        $log["RetornoMP"] = $payment;
        
        //$transactionStatus = 'Success';
        logTransaction(PAYMENT_METHOD, json_encode($log), "Pix Pago");
        
        addInvoicePayment(
                $idfatura,
                $payment_id,
                $paymentAmount,
                $paymentFee,
                PAYMENT_METHOD
            );
        
        }
    }
    
    http_response_code(200);
}