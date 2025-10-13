<?php

require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

$idfatura = filter_input(INPUT_GET, 'idfatura', FILTER_SANITIZE_NUMBER_INT);


try {

        $fatbd = Capsule::table('dfmercadopagopix')
                ->select('idfatura', 'idlocationpix', 'pixcopiacola', 'pixqrcode', 'valor')
                ->where('idfatura', '=', $idfatura)
                ->get();
                
                $image = base64_decode($fatbd[0]->pixqrcode);
                header('Content-Type: image/png');
                echo $image;
                

    } catch (\Exception $e) {
        echo "Fatura nao localizada";
    }

