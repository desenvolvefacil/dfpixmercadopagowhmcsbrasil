<?php
define("BASE_DIR", dirname(dirname(dirname(__FILE__))) . "/");

if (!defined("WHMCS")) {
    die();
}

require_once __DIR__ . "/../../init.php";

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;
use WHMCS\View\Menu\Item as MenuItem;


// defina o método de pagamento
define("PAYMENT_METHOD_MP_PIX", "dfpixmercadopago");

//dfpixmercadopago

function dfpixmercadopagocacelarpix($vars, $metodo)
{
    $modulo = Capsule::table("tblpaymentgateways")
        ->where("gateway", PAYMENT_METHOD_MP_PIX)
        ->where("setting", "type")
        ->where("value", "Invoices")
        ->first();

    if ($modulo) {
        //echo "Módulo '.PAYMENT_METHOD_MP_PIX.' está ATIVO!";

        $idfatura = trim($vars["invoiceid"]);

        $credentials = getGatewayVariables(PAYMENT_METHOD_MP_PIX);

        $access_token = $credentials["AccessTokenProducao"];

        //busca o id no banco para cancelar
        try {
            $fatbd = Capsule::table(PAYMENT_METHOD_MP_PIX)
                ->select(
                    "idfatura",
                    "idlocationpix",
                    "pixcopiacola",
                    "pixqrcode",
                    "valor"
                )
                ->where("idfatura", "=", $idfatura)
                ->get();

            if ($fatbd[0]->idlocationpix != "") {
                $IdLocationPix = $fatbd[0]->idlocationpix;

                $url =
                    "https://api.mercadopago.com/v1/payments/" . $IdLocationPix;

                $data = [
                    "status" => "cancelled",
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    json_encode($data, JSON_NUMERIC_CHECK)
                );
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: Bearer $access_token",
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $result = json_decode($response, true);

                $log = [];
                $log["IdFatura"] = $idfatura;
                $log["DadosBD"] = $fatbd;
                $log["RetornoMP"] = $result;

                logTransaction(
                    PAYMENT_METHOD_MP_PIX,
                    json_encode($log),
                    "Pix Cancelado|" . $metodo
                );
            }
        } catch (\Exception $e) {
        }

        //exclui a fatura do banco de dados
        Capsule::table(PAYMENT_METHOD_MP_PIX)
            ->select(
                "idfatura",
                "idlocationpix",
                "pixcopiacola",
                "pixqrcode",
                "valor"
            )
            ->where("idfatura", "=", $idfatura)
            ->delete();
    } else {
        //echo "Módulo '.PAYMENT_METHOD_MP_PIX.' está INATIVO!";
    }
}

function dfpifaturacancelada($vars)
{
    dfpixmercadopagocacelarpix($vars, "Fatura Cancelada");
}

function dfpifaturatualizada($vars)
{
    dfpixmercadopagocacelarpix($vars, "Fatura Atualizada");
}

add_hook("InvoiceCancelled", 1, "dfpifaturacancelada");
add_hook("UpdateInvoiceTotal", 1, "dfpifaturatualizada");



/**
 * Verifica se Existe Atualização do Modulos
 */
 
add_hook('AdminAreaHeaderOutput', 1, function($vars) {


    $jsonUrl = "https://raw.githubusercontent.com/desenvolvefacil/dfpixmercadopagowhmcsbrasil/main/version.json";
    $currentVersion = '3.0.1'; // versão atual do módulo
    
    $lastCheck = Setting::getValue(PAYMENT_METHOD_MP_PIX . '_last_update_check');
    $now = time();
    
    Setting::setValue(PAYMENT_METHOD_MP_PIX . '_last_update_check', $now);
    $latestVersion = $data['latest_version'];
    
    $response = @file_get_contents($jsonUrl);
    
    if (!$response) {
        logModuleCall(PAYMENT_METHOD_MP_PIX, 'Update Check Mercado Pago Pix', 'Falha ao buscar JSON', $jsonUrl);
        return;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['latest_version'])) {
        logModuleCall(PAYMENT_METHOD_MP_PIX, 'Update Check Mercado Pago Pix', 'JSON inválido', $response);
        return;
    }

    $latestVersion = $data['latest_version'];
    if (version_compare($latestVersion, $currentVersion, '>')) {
        //mais de 15 dias sem atualizar, envia email
        if(($now - $lastCheck) > 1296000){
            
            //Envial Email para Administrador
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
                    PAYMENT_METHOD_MP_PIX,
                    'SendAdminEmail',
                    $postData,
                    $results,
                    null
                );
        
            } catch (Exception $e) {
                logModuleCall(PAYMENT_METHOD_MP_PIX, 'Update Email Error', $e->getMessage(), '');
            }
            
        }
        
        //mostra mensagem no proprio WHMCS
        
        
        // ID do administrador (para diferenciar se vários admins usam o sistema)
        $adminId = isset($vars['adminid']) ? (int)$vars['adminid'] : 0;
    
        // Identificador único do cookie (inclui adminid e data)
        $cookieName = "admin_notice_" . $adminId . "_" . date('Ymd');
            

         $mensagem = "<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Verifica se o cookie já existe
        if (!document.cookie.includes('$cookieName')) {
            // Cria o alerta
            var alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible show text-center';
            alertDiv.setAttribute('role', 'alert');
            alertDiv.style.margin = '10px 0';
            alertDiv.innerHTML = `
                <p>Uma nova versão do módulo <b>Pix Mercado Pago</b> está disponível.</p>
                <p><b>Versão atual:</b> ".$currentVersion."<br>
                <b>Versão disponível:</b> ".$data["latest_version"]."</p>
                <p><b>Notas da versão:</b><br>".$data["release_notes"].".</p>
                <p>Baixar nova versão em: <a href='".$data["download_url"]."' target='_blank'>".$data["download_url"]."</a></p>
                <hr>
                <small>Desenvolve Fácil</small>
                <button type='button' class='close' data-dismiss='alert' aria-label='Fechar' style='position:absolute;right:15px;top:10px;'>
                    <span aria-hidden='true'>&times;</span>
                </button>
            `;
            // Adiciona ao topo do painel (antes do conteúdo principal)
            var container = document.querySelector('.content-area') || document.body;
            container.prepend(alertDiv);

            // Quando o usuário fechar, salva o cookie por 1 dia
            alertDiv.querySelector('.close').addEventListener('click', function() {
                var expires = new Date();
                expires.setHours(23, 59, 59, 999); // expira no fim do dia
                document.cookie = '$cookieName=1; expires=' + expires.toUTCString() + '; path=/';
            });
        }
    });
    </script>";
    
        return $mensagem;
        
        
        $mensagem = '<div class="alert alert-warning alert-dismissible  show text-center" role="alert"
        style="
                background-color: #ffef96;
                color: #333;
                border: 1px solid #f0c36d;
                padding: 10px 15px;
                border-radius: 5px;
                margin: 10px 0;
                font-size: 14px;
            ">
  
                        <p>Uma nova versão do módulo <b>Pix Mercado Pago</b> está disponível.</p>
                        <p><b>Versão atual:</b> '.$currentVersion.'<br>
                        <b>Versão disponível:</b> '.$data["latest_version"].'</p>
                        <p><b>Notas da versão:</b><br>'.$data["release_notes"].'.</p>
                        <p>Baixar nova versão em: <a href="'.$data["download_url"].'" target="_blank">'.$data["download_url"].'</a></p>
                        <hr>
                        <small>Desenvolve Fácil</small>
                <button type="button" class="close" data-dismiss="alert" aria-label="Fechar" style="position:absolute;right:15px;top:10px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
        
            return $mensagem;
        
    }
    

    return "";

});
