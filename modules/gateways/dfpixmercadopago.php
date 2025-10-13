<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


// defina o método de pagamento 
define('PAYMENT_METHOD', 'dfpixmercadopago');

// dfpixmercadopago


function dfpixmercadopago_MetaData() {
    return array(
        'DisplayName' => 'Pix Mercado Pago',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}


function dfpixmercadopago_config() {
    
     return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Pix Mercado Pago',
        ),


        'AccessTokenProducao' => array(
            'FriendlyName' => 'Access Token de Produção',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Coloque seu Access Token de Produção',
        ),


    );
}


function dfpixmercadopago_config_validate($params)
{
 
    if ($params['AccessTokenProducao'] == '') {
        throw new \Exception('O campo AccessTokenProducao não foi preenchido');
    }
        
   
    
    if(!Capsule::schema()->hasTable("dfmercadopagopix") ){
        try {
            
        Capsule::schema()->create(
            'dfmercadopagopix',
                function ($table) {
                    /** @var \Illuminate\Database\Schema\Blueprint $table */
                    $table->bigInteger('idfatura')->unsigned();
                    
                    $table->string('idlocationpix'); //numero do id gerado no mercado pago
                    $table->string('pixcopiacola');
                    $table->text('pixqrcode');
                    $table->decimal('valor');
                    
                    $table->primary('idfatura');
                }
            );
        } catch (\Exception $e) {
            throw new \Exception("Unable to create my_table: ".$e->getMessage());
        }
    }else{
        //throw new \Exception("Tabela já existe");
    }
    
    
}


function dfpixmercadopago_link($params) {
    
    global $CONFIG;
    //dados para retorno automatico
    $URL_PIX_RETORNO = $CONFIG['SystemURL'] . "/modules/gateways/callback/dfpixmercadopago-callback.php";

    $access_token = trim($params["AccessTokenProducao"]);
    //dados da fatura
    $idfatura = $params['invoiceid'];
    $valor = $params['amount'];
    $rederizarimagemqrcode = $CONFIG['SystemURL']."/modules/gateways/dfpixmercadopago/qrcode/".$idfatura;
    //dados pessoais
    $email = trim($params['clientdetails']['email']);
    
    $nome = trim($params['clientdetails']['firstname']);
    $sobrenome = trim($params['clientdetails']['lastname']);
    
    $documento =  trim($params['clientdetails']['customfields1']);
    $documento = preg_replace('/[^0-9]/', '', $documento);
    
    $tipodocumento = '';
    if(strlen($documento)==11){
        $tipodocumento = 'CPF';
    }
    
    if(strlen($documento)==14){
        $tipodocumento = 'CNPJ';
    }
    
    
    //verifica se ja existe a fatura no BD
    try {

        $fatbd = Capsule::table('dfmercadopagopix')
                ->select('idfatura', 'idlocationpix', 'pixcopiacola', 'pixqrcode', 'valor')
                ->where('idfatura', '=', $idfatura)
                ->get();
    } catch (\Exception $e) {
        
    }
    
    
    //$excluirpix = 0;
    
    $IdLocationPix = 0;
    $CopiaColaPix = "";
    $ImagemQrcode = "";
    $ValorFatura = 0.0;
    
    
    $currentDate = new DateTime();
    $interval = new DateInterval('P6M'); //6meses
    $futureDate = $currentDate->add($interval);
    $DataExpiracao = $futureDate->format("Y-m-d\TH:i:s.vP");
    
    $auxIdfatura = $idfatura;

    for (; $i < 26; $i++) {
        $auxIdfatura = '0' . $auxIdfatura;
    }

    $FaturaTexto = "DF" . $auxIdfatura;
    
    if ($fatbd[0]->idfatura > 0) {

        
        $IdLocationPix = $fatbd[0]->idlocationpix;
        $CopiaColaPix = $fatbd[0]->pixcopiacola;
        $ImagemQrcode = $fatbd[0]->pixqrcode;
        $ValorFatura = $fatbd[0]->valor;
        
        $CancelouFatura = 0;

        if ($ValorFatura != $valor) {
            
            //Cancela a Fatura Atual   
            $url = "https://api.mercadopago.com/v1/payments/".$IdLocationPix;

            $data = [
                "status" => "cancelled"
            ];
            

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_NUMERIC_CHECK));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $access_token"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
  
            $result = json_decode($response, true);
            
            
            $CopiaColaPix = "";
            $CancelouFatura = 1;
            
            $log = [];
            $log["IdFatura"] = $idfatura;
            $log["DadosBD"] = $fatbd;
            $log["RetornoMP"] = $result;
            
            logTransaction(PAYMENT_METHOD, json_encode($log), "Pix Cancelado|Geracao De Fatura");

        }
    }
    
    
    if($CopiaColaPix == ""){
        //gera um novo pix
        
        $url = "https://api.mercadopago.com/v1/payments";

        $data = [
            "transaction_amount" => $valor,
            "description"        => "Fatura #".$idfatura,
            "payment_method_id"  => "pix",
            "payer" => [
                "email" => $email,
                "first_name" => $nome,
                "last_name"  => $sobrenome,
                "identification" => [
                    "type"   => $tipodocumento,
                    "number" => $documento,
                ]
            ],
            "date_of_expiration" => $DataExpiracao,
            "external_reference" => $FaturaTexto,
            "notification_url" => $URL_PIX_RETORNO
        ];

        $key = preg_replace('/[^0-9]/', '', $DataExpiracao);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $access_token",
            "x-integrator-id: dev_5f464b885a5611f09813c2f1db30563c",
            "X-Idempotency-Key: $key"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_NUMERIC_CHECK)); //utilizar JSON_NUMERIC_CHECK
        // Executa requisição
        $response = curl_exec($ch);
       
        // Fecha conexão
        curl_close($ch);
        
        // Converte resposta em array
        $result = json_decode($response, true);
  
        $IdLocationPix = $result["id"];
        $CopiaColaPix = $result['point_of_interaction']['transaction_data']['qr_code'];
        $ImagemQrcode = $result['point_of_interaction']['transaction_data']['qr_code_base64'];
        
        
        
        $log = [];
        $log["IdFatura"] = $idfatura;
        $log["RetornoMP"] = $result;
        
        logTransaction(PAYMENT_METHOD,json_encode($log), "Pix Gerado");
        
        if($CancelouFatura == 0){
            
        
            Capsule::table('dfmercadopagopix')->insert(
                    [
                        'idfatura' => $idfatura,
                        'idlocationpix' => $IdLocationPix,
                        'pixcopiacola' => $CopiaColaPix,
                        'pixqrcode' => $ImagemQrcode,
                        'valor' => $valor
                    ]
            );
            
        }else{
            
            Capsule::table('dfmercadopagopix')->where('idfatura', $idfatura)
                ->update(
                        [
                            'idlocationpix' => $IdLocationPix,
                            'pixcopiacola' => $CopiaColaPix,
                            'pixqrcode' => $ImagemQrcode,
                            'valor' => $valor
                        ]
        );
            
        }
        
    }
    
    
    
    $htmlOutput = '<script type="text/javascript">
        function copiarPix() {

        link = "' . $CopiaColaPix . '";

        navigator.clipboard.writeText(link).then(
            () => {
                alert("Codigo Pix Copiado: " + link);
            },
            () => {
                /* clipboard write failed */
            },
        );
        }
        
        setTimeout(function() {
          location.reload();
        }, 10000);
    </script>';

    $formatter = new NumberFormatter('pt_BR', NumberFormatter::CURRENCY);

    $htmlOutput .= '<p>
    <img alt="" style="max-width:150px;" src="data:image/png;base64,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" /></p>'
            . '<p /><p>Total a Pagar: <br/><b>' . $formatter->formatCurrency($valor, 'BRL') . '</b></p><p /><p />'
            . '<p>' . '<img style="max-width: 300px;" src="' . $rederizarimagemqrcode . '">' . '</p>'
            . '<p>Pix Copia e Cola... (Clique para copiar o codigo)</p>'
            . '<input style="max-width: 300px;" type="button" onclick="javascript:copiarPix();" value="' . $CopiaColaPix . '" />'
            . '<p /><p /><p />'
            . '<textarea name="textarea"
   rows="5" cols="30"
   minlength="10" maxlength="20">' . $CopiaColaPix . '</textarea>'
            . '</p><p/><hr />';


    return $htmlOutput;
}


function dfpixmercadopago_refund($params)
{
 
    $access_token = $params["AccessTokenProducao"];
 
     $idfatura = $params['invoiceid'];
     $refundAmount = $params['amount'];
 
 
  //verifica se ja existe a fatura no BD
    try {

        $fatbd = Capsule::table('dfmercadopagopix')
                ->select('idfatura', 'idlocationpix', 'pixcopiacola', 'pixqrcode', 'valor')
                ->where('idfatura', '=', $idfatura)
                ->get();
                
        $IdLocationPix = $fatbd[0]->idlocationpix;
        
        
        
        $url = "https://api.mercadopago.com/v1/payments/".$IdLocationPix."/refunds";

        $data = [
            "amount" => $refundAmount // valor em BRL a ser reembolsado
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // corpo vazio = reembolso total
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $access_token"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        
        logTransaction(PAYMENT_METHOD,"httpCode = ". $httpCode, "Pix Reembolsado");
        logTransaction(PAYMENT_METHOD,json_decode($response, true), "Pix Reembolsado");
        
        if ($httpCode == 201) {
            $result = json_decode($response, true);
            //echo "Reembolso criado! Refund ID: " . $result["id"] . " | Status: " . $result["status"];
            
            
            
            $log = [];
            $log["IdFatura"] = $idfatura;
            $log["DadosBD"] = $fatbd;
            $log["RetornoMP"] = $result;
            
            logTransaction(PAYMENT_METHOD,json_encode($log), "Pix Reembolsado");
            
            return array(
                // 'success' if successful, otherwise 'declined', 'error' for failure
                'status' => 'success',
                // Data to be recorded in the gateway log - can be a string or array
                'rawdata' => json_encode($result) ,
                // Unique Transaction ID for the refund transaction
                'transid' => $result["id"],
                // Optional fee amount for the fee value refunded
                'fees' => 0,
            );
            
        } else {
            //echo "Erro ao reembolsar pagamento. HTTP $httpCode: $response";
            return array(
                // 'success' if successful, otherwise 'declined', 'error' for failure
                'status' => 'error',
                // Data to be recorded in the gateway log - can be a string or array
                'rawdata' => json_encode($response) ,
                // Unique Transaction ID for the refund transaction
                'transid' => $response["id"],
                // Optional fee amount for the fee value refunded
                'fees' => 0,
            );
        }
        

    } catch (\Exception $e) {
        
    }
    
}




