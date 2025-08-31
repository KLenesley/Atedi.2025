<?php

namespace App\Util;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DolibarrHelper
{

    private $httpClient;
    private $DOLIBARR_URL;
    private $DOLIBARR_APIKEY;
    private $TAUX_TVA;
    private $flashBag;

    public function __construct(ParameterBagInterface $params, FlashBagInterface $flashBag)
    {
        ////////////////////////////////////////////////////////////////
        // @TODO à retirer
        // UPDATE tbl_intervention SET STATUS='En cours' WHERE id = 2;
        ////////////////////////////////////////////////////////////////

        // Créer l'objet HttpClient
        $this->httpClient = HttpClient::create();

        $this->DOLIBARR_URL = $params->get('DOLIBARR_URL');
        if (substr($this->DOLIBARR_URL, -1) != '/') {
            $this->DOLIBARR_URL .= '/';
        }
        $this->DOLIBARR_APIKEY = $params->get('DOLIBARR_APIKEY');

        $this->TAUX_TVA = $params->get('TAUX_TVA');

        $this->flashBag = $flashBag;
    }

    public function getDolibarrClientId($client)
    {
        $dolibarrClientId = null;

        try {
            $client_name = trim($client->getFirstName() . ' ' . $client->getLastName());

            $action = 'la recherche du client dans Dolibarr';
            $this->flashBag->add('info', "Recherche du client '" . $client_name . "' dans Dolibarr...");

            // Exécuter la requête
            $response = $this->httpClient->request('GET', $this->DOLIBARR_URL . 'api/index.php/thirdparties?DOLAPIKEY=' . $this->DOLIBARR_APIKEY . '&sqlfilters=t.nom:=:\'' . $client_name . '\'&limit=1');

            // Afficher le code de retour
            $statusCode = $response->getStatusCode();
            $action .= " -> statusCode = '".$statusCode."'";
            if ($statusCode != 404) {

                // Afficher l'entête de la réponse
                $contentType = $response->getHeaders()['content-type'][0];
                /* $this->flashBag->add('info', $contentType); */

                // Afficher le contenu JSON de la réponse
                $content = $response->getContent();
                /* $this->flashBag->add('info', $content); */

                // Afficher le contenu OBJET de la réponse
                $content_decode = json_decode($content);
                /* $this->flashBag->add('info', print_r($content_decode, true)); */

                // ID du client
                $dolibarrClientId = $content_decode[0]->id;
                $this->flashBag->add('info', "ID du client = " . $dolibarrClientId);
            } else {
                $action = 'la création du client dans Dolibarr';
                $this->flashBag->add('info', "Le client '" . $client_name . "' n'a pas trouvé, ajout du client dans Dolibarr...");

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
                $this->flashBag->add('info', $statusCode);

                // Afficher l'entête de la réponse
                $contentType = $response->getHeaders()['content-type'][0];
                /* $this->flashBag->add('info', $contentType); */

                // Afficher le contenu JSON de la réponse
                $dolibarrClientId = $response->getContent();
                $this->flashBag->add('info', "ID du client qui vient d'être créé : " . $dolibarrClientId);
            }
        } catch (\Throwable $th) {
            // $this->flashBag->add('error', 'Une erreur est intervenue lors de ' . $action . ' : ' . $th->getMessage());
            $this->flashBag->add('error', 'Une erreur est intervenue lors de ' . $action);
        }

        return $dolibarrClientId;
    }

    public function getDolibarrProductServiceId($product, $type)
    {
        $dolibarrProductId = null;

        if ($type == 'service') {
            $type = 1;
        } else {
            $type = 0;
        }

        try {
            $product_name = $product->getTitle();

            $action = 'la recherche de la tâche dans Dolibarr';
            $this->flashBag->add('info', "Recherche du product '" . $product_name . "' dans Dolibarr...");

            // Exécuter la requête
            $response = $this->httpClient->request('GET', $this->DOLIBARR_URL . 'api/index.php/products?DOLAPIKEY=' . $this->DOLIBARR_APIKEY . '&sqlfilters=t.label:=:\'' . 'Intervention - ' . $product_name . '\'&limit=1');

            // Afficher le code de retour
            $statusCode = $response->getStatusCode();

            $content = $response->getContent();
            $content_decode = json_decode($content);

            if ($statusCode != 404 & sizeof($content_decode) != 0) {

                // Afficher l'entête de la réponse
                $contentType = $response->getHeaders()['content-type'][0];
                /* $this->flashBag->add('info', $contentType); */

                // Afficher le contenu JSON de la réponse
                $content = $response->getContent();
                $this->flashBag->add('info', $content);

                // Afficher le contenu OBJET de la réponse
                $content_decode = json_decode($content);
                $this->flashBag->add('info', print_r($content_decode, true));

                // ID du product                
                    $dolibarrProductId = $content_decode[0]->id;
                    $this->flashBag->add('info', "ID du product = " . $dolibarrProductId);
            } else {
                $action = 'la création de la tâche dans Dolibarr';
                $this->flashBag->add('info', "Le product '" . $product_name . "' n'a pas trouvé, ajout du product dans Dolibarr...");

                $ref = 'ATEDI-' . str_pad($product->getId(), 3, "0", STR_PAD_LEFT);
                $barcode = '999' . str_pad($product->getId(), 10, "0", STR_PAD_LEFT);
                $price = round(($product->getPrice() / (1 + ($this->TAUX_TVA / 100))), 2);
                $price_ttc = round($product->getPrice(), 2);
                $tva_tx = $this->TAUX_TVA;
                
                //// $this->flashBag->add('info', "ref = '" . $ref . "' label = '" . 'Intervention - ' . $product_name . "' type = '" . $type  . "' price = '" . $price . "' price_ttc = '" . $price_ttc . "' tva_tx = '" . $tva_tx . "' " . "' barcode = '" . $barcode . "' ");
                $response = $this->httpClient->request('POST', $this->DOLIBARR_URL . 'api/index.php/products?DOLAPIKEY=' . $this->DOLIBARR_APIKEY, [
                    'body' => [
                        'ref' => $ref,
                        'label' => 'Intervention - ' . $product_name,
                        'description' => 'Intervention - ' . $product_name,
                        'type' => $type,
                        'price' => $price,
                        'price_ttc' => $price_ttc,
                        'price_base_type' => 'TTC',
                        'pmp' => $price_ttc,
                        'tva_tx' => $tva_tx,
                        'status' => 1, //tosell
                        'status_buy' => 1,
                        'barcode_type' => 2,
                        'barcode' => $barcode, //9662247182140
                    ],
                ]);

                // Afficher le code de retour
                $statusCode = $response->getStatusCode();
                $this->flashBag->add('info', $statusCode);

                // Afficher l'entête de la réponse
                $contentType = $response->getHeaders()['content-type'][0];
                /* $this->flashBag->add('info', $contentType); */

                // Afficher le contenu JSON de la réponse
                $dolibarrProductId = $response->getContent();
                $this->flashBag->add('info', "ID du product qui vient d'être créé : " . $dolibarrProductId);
            }
        } catch (\Throwable $th) {
            // $this->flashBag->add('error', 'Une erreur est intervenue lors de ' . $action . ' : ' . $th->getMessage());
            $this->flashBag->add('error', 'Une erreur est intervenue lors de ' . $action);
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
                }
                else {
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
            $this->flashBag->add('info', $statusCode);

            // Afficher l'entête de la réponse
            $contentType = $response->getHeaders()['content-type'][0];
            // /* $this->flashBag->add('info', $contentType); */

            // Afficher le contenu JSON de la réponse
            $dolibarrFactureId = $response->getContent();
            // $this->flashBag->add('info', "ID du product qui vient d'être créé : " . $dolibarrProductId);

        } catch (\Throwable $th) {
            // $this->flashBag->add('error', 'Une erreur est intervenue lors de la création de la facture dans Dolibarr : ' . $th->getMessage());
            $this->flashBag->add('error', 'Une erreur est intervenue lors de la création de la facture dans Dolibarr');
        }

        return $dolibarrFactureId;
    }
}