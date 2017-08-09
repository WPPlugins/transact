<?php

use Transact\FrontEnd\Controllers\Api\TransactApi;
require_once __DIR__ . '/../transact-api.php';

$transact = new TransactApi();
$transact = $transact->returnTransactIoMsg();

// all of our responses are JSOn
header('Content-Type: text/javascript; charset=utf8');

switch($_REQUEST['action']) {

    case 'getToken':
        $transact = InitSaleParameters($transact);
        $response = array(
            'token' => $transact->getToken()
        );
        echo json_encode($response);
        break;

    case 'getPurchasedContent':

        try {
            $decoded = $transact->decodeToken($_REQUEST['t']);
            echo json_encode(array(
                'content' => 'SUCESSS PAID CONTENT HERE!',
                'status' => 'OK',
                'decoded' => $decoded
            ));
        } catch (Exception $e) {

            echo json_encode(array(
                'content' => 'Failed validation',
                'status' => 'ERROR',
                'message' =>  $e->getMessage(),
            ));
        }

        break;
    default:
        header("HTTP/1.0 404 Not Found");

        echo json_encode(new ErrorResponse('404', 'Invalid API call'));
        exit;
};

