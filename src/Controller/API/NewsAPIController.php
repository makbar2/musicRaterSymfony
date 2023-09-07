<?php

namespace App\Controller\API;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewsAPIController extends AbstractController
{
    private $client;
    private $apiKey;
    public function  __construct()
    {
        $this->client = new Client([
            "base_uri" => "https://newsapi.org/v2/",
        ]);
        $this->apiKey = $_ENV["NEWSAPI_KEY"];
    }


    public function getMusicNews()
    {
        $response = $this->client->request("GET","everything",[
            "query" => [
                "sources" => "bbc-news",
                "q" => '"music","songs"',
                "apiKey" => $this->apiKey
            ]
        ]);
        return json_decode($response->getBody(),true);
    }
}
