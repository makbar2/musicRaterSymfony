<?php

namespace App\Controller;

use App\Controller\API\NewsAPIController;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class HomeController extends AbstractController
{
    /**
     * controller for the home page and search,
     * search is here since its apart of the nav bar
     */


    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $news = new NewsAPIController();
        $data = $news->getMusicNews();//get music articles from bbc news]

        return $this->render('home/index.html.twig', [
            "data" => $data["articles"],
            'controller_name' => 'HomeController',
        ]);
    }

    /**
     * can search for users,artists, albums
     * this handles the requests from the search bar, which has a form hardcoded into it since idk how to
     * since i would have to havbe the search bar's controller imported into every controller
     * its better to have form redirect to this route and it do a get request
     */
    #[Route('/search', name: 'app_search')]
    public function search(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        if ($request->query->get("search") != null)//if the user just enters /search into the url bar stops them from getting an error
        {
            $q = $slugger->slug($request->query->get("search"));
            $albums = $doctrine->getRepository(Album::class)->getNameLike($q);
            $artists = $doctrine->getRepository(Artist::class)->getNameLike($q);
            $users = $doctrine->getRepository(User::class)->getNameLike($q);
        }else
        {
            $albums = null;
            $users = null;
            $artists = null;
        }
        return $this->render("home/search.html.twig",
            [
                    "albums" => $albums,
                    "users" => $users,
                    "artists" => $artists
            ]
        );
    }




}


