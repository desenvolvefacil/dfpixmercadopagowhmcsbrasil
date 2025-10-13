<?php
define("BASE_DIR", dirname(dirname(dirname(__FILE__))) . "/");

if (!defined("WHMCS")) {
    die();
}

require_once __DIR__ . "/../../init.php";

use WHMCS\Database\Capsule;

// defina o método de pagamento
define("PAYMENT_METHOD", "dfpixmercadopago");

//dfpixmercadopago

function dfpixmercadopagocacelarpix($vars, $metodo)
{
    $modulo = Capsule::table("tblpaymentgateways")
        ->where("gateway", PAYMENT_METHOD)
        ->where("setting", "type")
        ->where("value", "Invoices")
        ->first();

    if ($modulo) {
        //echo "Módulo '.PAYMENT_METHOD.' está ATIVO!";

        $idfatura = trim($vars["invoiceid"]);

        $credentials = getGatewayVariables(PAYMENT_METHOD);

        $access_token = $credentials["AccessTokenProducao"];

        //busca o id no banco para cancelar
        try {
            $fatbd = Capsule::table("dfmercadopagopix")
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
                    PAYMENT_METHOD,
                    json_encode($log),
                    "Pix Cancelado|" . $metodo
                );
            }
        } catch (\Exception $e) {
        }

        //exclui a fatura do banco de dados
        Capsule::table("dfmercadopagopix")
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
        //echo "Módulo '.PAYMENT_METHOD.' está INATIVO!";
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
