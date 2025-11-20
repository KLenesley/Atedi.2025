<?php

namespace App\Util;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use App\Service\FlashMessageService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DolibarrHelper
{

    private $httpClient;
    private $DOLIBARR_URL;
    private $DOLIBARR_APIKEY;
    private $TAUX_TVA;
    private $notifier;

    public function __construct(ParameterBagInterface $params, private readonly FlashMessageService $flashMessageService)
    {
        ////////////////////////////////////////////////////////////////
        // @TODO à retirer
        // UPDATE tbl_intervention SET STATUS='En cours' WHERE id = 2;
        ////////////////////////////////////////////////////////////////

        // Créer l'objet HttpClient
        $this->httpClient = HttpClient::create();

        $this->DOLIBARR_URL = $params->get('DOLIBARR_URL');
        if (substr($this->DOLIBARR_URL, -1) !== '/') {
            $this->DOLIBARR_URL .= '/';
        }
        $this->DOLIBARR_APIKEY = $params->get('DOLIBARR_APIKEY');

        $this->TAUX_TVA = $params->get('TAUX_TVA');
    }

    public function getDolibarrClientId($client)
    {
        $dolibarrClientId = null;

        try {
            $client_name = trim($client->getFirstName() . ' ' . $client->getLastName());

            $action = 'la recherche du client dans Dolibarr';
            $this->flashMessageService->addSuccess("Recherche du client '" . $client_name . "' dans Dolibarr...");

            // Exécuter la requête
            $response = $this->httpClient->request('GET', $this->DOLIBARR_URL . 'api/index.php/thirdparties?DOLAPIKEY=' . $this->DOLIBARR_APIKEY . '&sqlfilters=t.nom:=:\'' . $client_name . '\'&limit=1');

            // Afficher la requête envoyée à Dolibarr
            // $this->flashMessageService->addSuccess("Requête envoyée à Dolibarr : " . $this->DOLIBARR_URL . 'api/index.php/thirdparties?DOLAPIKEY=' . $this->DOLIBARR_APIKEY . '&sqlfilters=t.nom:=:\'' . $client_name . '\'&limit=1');

            // Afficher la réponse complète de Dolibarr
            // $this->flashMessageService->addSuccess("1) Réponse de Dolibarr '" . print_r($response, true));
            // dump("1) Réponse de Dolibarr '" . print_r($response, true));

            // Afficher le code de retour
            $statusCode = $response->getStatusCode();
            $action .= " -> statusCode = '" . $statusCode . "'";
            if ($statusCode != 404) {

                // Afficher l'entête de la réponse
                $contentType = $response->getHeaders()['content-type'][0];

                // Afficher le contenu JSON de la réponse
                $content = $response->getContent();

                // Afficher le contenu OBJET de la réponse
                $content_decode = json_decode($content);

                // ID du client
                $dolibarrClientId = $content_decode[0]->id;
                $this->flashMessageService->addSuccess("ID du client = " . $dolibarrClientId);
            } else {
                $action = 'la création du client dans Dolibarr';
                $this->flashMessageService->addSuccess("Le client '" . $client_name . "' n'a pas trouvé, ajout du client dans Dolibarr...");

                $response = $this->httpClient->request('POST', $this->DOLIBARR_URL . 'api/index.php/thirdparties?DOLAPIKEY=' . $this->DOLIBARR_APIKEY, [
                    'body' => [
                        'client' => 1,
                        'code_client' => -1,
                        'name' => $client_name,
                        'address' => $client->getStreet(),
                        'zip' => $client->getPostalCode(),
                        'town' => $client->getCity(),
                        'status' => 1,
                        'email' => $client->getEmail(),
                        'phone' => $client->getPhone(),
                    ],
                ]);

                // Afficher le code de retour
                $statusCode = $response->getStatusCode();
                $this->flashMessageService->addSuccess("Code de retour : " . (string)$statusCode);

                // Afficher le contenu JSON de la réponse
                $dolibarrClientId = $response->getContent();
                $this->flashMessageService->addSuccess("ID du client qui vient d'être créé : " . $dolibarrClientId);
            }
        } catch (\Throwable $th) {
            $this->flashMessageService->addSuccess('Une erreur est intervenue lors de ' . $action, ['email']);
        }

        return $dolibarrClientId;
    }

    public function getDolibarrProductServiceId($product, $type)
    {
        $dolibarrProductId = null;

        $type = $type == 'service' ? 1 : 0;

        try {
            $product_name = $product->getTitle();

            $action = 'la recherche du ' . ($type == 1 ? 'service' : 'produit') . ' dans Dolibarr';
            $this->flashMessageService->addSuccess("Recherche du " . ($type == 1 ? 'service' : 'produit') . " '" . $product_name . "' dans Dolibarr...");

            // Exécuter la requête
            $response = $this->httpClient->request('GET', $this->DOLIBARR_URL . 'api/index.php/products?DOLAPIKEY=' . $this->DOLIBARR_APIKEY . '&sqlfilters=t.label:=:\'' . 'Intervention - ' . $product_name . '\'&limit=1');

            // Afficher le code de retour
            $statusCode = $response->getStatusCode();

            $content = $response->getContent();
            $content_decode = json_decode($content);

            if (($statusCode != 404 & count($content_decode) !== 0) !== 0) {

                // Afficher le contenu JSON de la réponse
                $content = $response->getContent();
                $this->flashMessageService->addSuccess($content);

                // Afficher le contenu OBJET de la réponse
                $content_decode = json_decode($content);
                $this->flashMessageService->addSuccess(print_r($content_decode, true));

                // ID du product
                $dolibarrProductId = $content_decode[0]->id;
                $this->flashMessageService->addSuccess("ID du product = " . $dolibarrProductId);
            } else {
                $action = 'la création du ' . ($type == 1 ? 'service' : 'produit') . ' dans Dolibarr';
                $this->flashMessageService->addSuccess("Le " . ($type == 1 ? 'service' : 'produit') . " '" . $product_name . "' n'a pas trouvé, ajout du " . ($type == 1 ? 'service' : 'produit') . " dans Dolibarr...");

                $ref = 'ATEDI-' . str_pad($product->getId(), 3, "0", STR_PAD_LEFT);
                $barcode = '999' . str_pad($product->getId(), 10, "0", STR_PAD_LEFT);
                $price = round(($product->getPrice() / (1 + ($this->TAUX_TVA / 100))), 2);
                $price_ttc = round($product->getPrice(), 2);
                $tva_tx = $this->TAUX_TVA;

                $response = $this->httpClient->request('POST', $this->DOLIBARR_URL . 'api/index.php/products?DOLAPIKEY=' . $this->DOLIBARR_APIKEY, [
                    'body' => [
                        'ref' => $ref,
                        'label' => 'Intervention - ' . $product_name,
                        'description' => 'Intervention - ' . $product_name,
                        'fk_product_type' => $type,
                        'price' => $price,
                        'price_ttc' => $price_ttc,
                        'price_base_type' => 'TTC',
                        'pmp' => $price_ttc,
                        'tva_tx' => $tva_tx,
                        'tosell' => 1,
                        'tobuy' => 1,
                        'tobatch' => 1,
                        'fk_barcode_type' => 2,
                        'barcode' => $barcode,
                    ],
                ]);

                // Afficher le code de retour
                $statusCode = $response->getStatusCode();
                $this->flashMessageService->addSuccess("Code de retour : " . (string)$statusCode);

                // Afficher la requête envoyée à Dolibarr
                // $this->flashMessageService->addSuccess("Requête envoyée à Dolibarr : " . $this->DOLIBARR_URL . 'api/index.php/products?DOLAPIKEY=' . $this->DOLIBARR_APIKEY);

                // Afficher la réponse complète de Dolibarr
                // $this->flashMessageService->addSuccess("2) Réponse de Dolibarr '" . print_r($response, true));
                // dump("2) Réponse de Dolibarr '" . print_r($response, true));

                // Afficher le contenu JSON de la réponse
                $dolibarrProductId = $response->getContent();
                $this->flashMessageService->addSuccess("ID du product qui vient d'être créé : " . $dolibarrProductId);
            }
        } catch (\Throwable $th) {
            $this->flashMessageService->addSuccess('Une erreur est intervenue lors de ' . $action, ['email']);
        }

        return $dolibarrProductId;
    }

    public function getDolibarrFactureId($intervention, $dolibarrClientId, $dolibarrLignesFacture)
    {
        $dolibarrFactureId = null;

        try {

            $note_public = "";
            $note_private = "";

            // Préparer la liste des lignes de la facture
            $lignesfacture = array();
            foreach ($dolibarrLignesFacture as $key => $value) {
                $price = ($value->getPrice() / (1 + ($this->TAUX_TVA / 100)));
                $tva_tx = $this->TAUX_TVA;
                $fk_product = $key;
                if ($fk_product < 0) {
                    $fk_product = 0;
                    $note_public .= $value->getTitle() . "\n";
                } else {
                    $note_public .= "Intervention : " . $value->getTitle() . "\n";
                }
                $lignesfacture[] = [
                    'desc' => $value->getTitle(),
                    'subprice' => round($price, 2),
                    'qty' => 1,
                    'tva_tx' => $tva_tx,
                    'fk_product' => $fk_product,
                ];
            }
            $note_private .= "Facture créée automatiquement par ATEDI" . "\n";
            $note_private .= $intervention->getInterventionReport()->getComment();

            // Exécuter la requête
            $response = $this->httpClient->request('POST', $this->DOLIBARR_URL . 'api/index.php/invoices?DOLAPIKEY=' . $this->DOLIBARR_APIKEY, [
                'body' => [
                    'socid' => $dolibarrClientId,
                    'type' => 0,
                    'note_public' => trim($note_public),
                    'note_private' => $note_private,
                    'lines' => $lignesfacture,
                ],
            ]);

            // Afficher le code de retour
            $statusCode = $response->getStatusCode();
            $this->flashMessageService->addSuccess("Code de retour : " . (string)$statusCode);

            // Afficher le contenu JSON de la réponse
            $dolibarrFactureId = $response->getContent();
        } catch (\Throwable $th) {
            $this->flashMessageService->addSuccess('Une erreur est intervenue lors de la création de la facture dans Dolibarr', ['email']);
        }

        return $dolibarrFactureId;
    }
}
