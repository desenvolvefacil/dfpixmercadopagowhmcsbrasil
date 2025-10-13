<?php

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// defina o método de pagamento 
define('PAYMENT_METHOD_MP_PIX', 'dfpixmercadopago');

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


// =========================
// VERIFICA ATUALIZAÇÃO
// =========================
function dfpixmercadopago_check_update() {
 
    $jsonUrl = "https://raw.githubusercontent.com/desenvolvefacil/dfpixmercadopagowhmcsbrasil/main/version.json";
    $currentVersion = '2.0.0'; // versão atual do módulo
    
    $lastCheck = Setting::getValue(PAYMENT_METHOD_MP_PIX . '_last_update_check');
    $now = time();
    
    if (!$lastCheck || ($now - $lastCheck) > 1296000) {
        Setting::setValue(PAYMENT_METHOD_MP_PIX . '_last_update_check', $now);

        $response = @file_get_contents($jsonUrl);
        if (!$response) {
            logModuleCall(PAYMENT_METHOD_MP_PIX, 'Update Check', 'Falha ao buscar JSON', $jsonUrl);
            return;
        }

        $data = json_decode($response, true);
        if (!isset($data['latest_version'])) {
            logModuleCall(PAYMENT_METHOD_MP_PIX, 'Update Check', 'JSON inválido', $response);
            return;
        }

        $latestVersion = $data['latest_version'];
        if (version_compare($latestVersion, $currentVersion, '>')) {
            dfpixmercadopago_send_update_email($data, $currentVersion);
        }
    }
}


// =========================
// ENVIA E-MAIL ADMIN VIA API WHMCS
// =========================
function dfpixmercadopago_send_update_email($data, $currentVersion) {
    try {
        // 🔹 Define dados para o e-mail
        $postData = [
            'action' => 'SendAdminEmail',
            'customsubject' => 'Atualização disponível: Pix Mercado Pago WHMCS',
            'custommessage' => "
                <p>Olá,</p>
                <p>Uma nova versão do módulo <b>Pix Mercado Pago WHMCS</b> está disponível.</p>
                <p><b>Versão atual:</b> {$currentVersion}<br>
                <b>Versão disponível:</b> {$data['latest_version']}</p>
                <p><b>Notas da versão:</b><br>{$data['release_notes']}</p>
                <p>Baixar nova versão em: <a href='{$data['download_url']}' target='_blank'>{$data['download_url']}</a></p>
                <hr>
                <small>Desenvolve Fácil</small>
            ",
            'type' => 'system'
        ];

        // 🔹 Chama a API interna
        $results = localAPI('SendAdminEmail', $postData, $adminUsername);

        // 🔹 Loga o resultado
        logModuleCall(
            'dfpixmercadopago',
            'SendAdminEmail',
            $postData,
            $results,
            null
        );

    } catch (Exception $e) {
        logModuleCall('dfpixmercadopago', 'Update Email Error', $e->getMessage(), '');
    }
}





function dfpixmercadopago_link($params) {
    
    dfpixmercadopago_check_update();
    
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
            
            logTransaction(PAYMENT_METHOD_MP_PIX, json_encode($log), "Pix Cancelado|Geracao De Fatura");

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
        
        logTransaction(PAYMENT_METHOD_MP_PIX,json_encode($log), "Pix Gerado");
        
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
    <img alt="" style="max-width:150px;" src="'.$CONFIG['SystemURL'].'/modules/gateways/dfpixmercadopago/logopix.png" /></p>'
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
        
        
        logTransaction(PAYMENT_METHOD_MP_PIX,"httpCode = ". $httpCode, "Pix Reembolsado");
        logTransaction(PAYMENT_METHOD_MP_PIX,json_decode($response, true), "Pix Reembolsado");
        
        if ($httpCode == 201) {
            $result = json_decode($response, true);
            //echo "Reembolso criado! Refund ID: " . $result["id"] . " | Status: " . $result["status"];
            
            
            
            $log = [];
            $log["IdFatura"] = $idfatura;
            $log["DadosBD"] = $fatbd;
            $log["RetornoMP"] = $result;
            
            logTransaction(PAYMENT_METHOD_MP_PIX,json_encode($log), "Pix Reembolsado");
            
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
