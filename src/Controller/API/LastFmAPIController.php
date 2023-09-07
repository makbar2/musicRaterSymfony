<?php

namespace App\Controller\API;

use App\Entity\Album;
use App\Entity\Artist;
use Doctrine\Persistence\ManagerRegistry;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use GuzzleHttp\Client;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
class LastFmAPIController extends AbstractFOSRestController
{
    private $client;
    private $apiKey;
    public function  __construct()
    {
        $this->client = new Client([
            "base_uri" => "http://ws.audioscrobbler.com/2.0/",
        ]);
        $this->apiKey = $_ENV["LASTFM_KEY"];
    }


    /**
     * returns as associative array of the top 50 tracks by an artist.
     * @param string $name artist name
     * @return array, top 50 tracks
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getArtistsTopTracks(string $name):array
    {
        $response = $this->client->get("",[
            "query" => [
                "method" => "artist.getTopTracks",
                "api_key" => $this->apiKey,
                "artist" => $name,
                "format" => "json",
            ]
        ]);

        return json_decode($response->getBody(),true);
    }

    /**
     * gets the information about an album from last fm
     * artist name because there could be multiple albums with the same name
     * @param string $albumName album name
     * @param string $artistName artist name
     * @return array album info
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAlbumInfo(string $albumName, string $artistName):array
    {
        $response = $this->client->get("", [
            "query" => [
                "method" => "album.getInfo",
                "api_key" => $this->apiKey,
                "artist" => $artistName,
                "album" => $albumName,
                "format" => "json"
            ]
        ]);
        return json_decode($response->getBody(),true);
    }





}
