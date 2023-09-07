<?php

namespace App\Controller;

use App\Controller\API\LastFmAPIController;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Review;
use App\Entity\Tracks;
use App\Form\AlbumType;
use App\Form\ArtistType;
use App\Form\ReviewType;
use App\Interface\MusicInterface;
use App\Interface\SubmissionInterface;
use App\Service\EntityUtil;
use App\Service\PictureUploader;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use PhpParser\Node\Stmt\Return_;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\Date;

class MusicController extends AbstractController
{

    /**
     * for stuff relating to albums and home page
     * add admin utilities relating to albums
     */


    #[Route('/add/album/{error}', name: 'app_music_add_album')]
    public function addAlbum(Request $request,PictureUploader $pc,SluggerInterface $slugger,int $error=0): Response
    {
        /**
         * @todo: track upload
         * log the user that has uploaded the album
         */
        $entityManager = $this->getDoctrine()->getManager();
       $album = new Album();
       $form = $this->createForm(AlbumType::class,$album);
       $form->handleRequest($request);
       $currentForm = $form;
       if($form->isSubmitted() && $form->isValid())
       {
           //add the album first then add the tracks pass in the id for the album
           if($form["lastFM_Auto_Complete"]->getData() == false)
           {
               $result = $this->processALbum($form, $pc,$slugger);
           }else{

               $result = $this->processLastFMRequestALbum($form, $pc,$slugger);
           }

           if($result != 0)
           {
               return $this->redirectToRoute("app_music_add_album",["error" =>$result]);
           }else{
               return $this->redirectToRoute("app_home");
           }

       }
       return $this->renderForm('music/add.html.twig', [
           "form" => $currentForm,
           "error" => $error
        ]);

    }

    private function processALbum(FormInterface $form, PictureUploader $pc,SluggerInterface $slugger): int
    {
        $entityManager = $this->getDoctrine()->getManager();
        $uploadedPicture = $form["picture"]->getData();
        $fileName = $pc->upload($uploadedPicture);
        $album = $form->getData();
        if($form["numTracks"]->getData() !== null && $form["trackNames"]->getData() !== null && $form["trackLengths"]->getData() !== null) {
            $tracks = $form["numTracks"]->getData();
            $names = $form["trackNames"]->getData();
            $lengths = $form["trackLengths"]->getData();
            $names = explode(",", $names);
            $tracks = explode(",", $tracks);
            $lengths = explode(",", $lengths);
            //set the album
            $album->setPicture($fileName);
            $entityManager->persist($album);
            $entityManager->flush($album);
            //make track class
            for ($i = 0; $i < count($names); $i++) {
                $song = new Tracks();
                $song->setSongName($slugger->slug($names[$i]));

                $song->setDuration(new \DateTime($lengths[$i]));
                $song->setAlbum($album);
                $entityManager->persist($song);
                $entityManager->flush($song);
            }
            return 0;
        }else{
            return 2;
        }
    }

    /**
     *
     * @param FormInterface $form form from add album 
     * @param PictureUploader $pc service injection 
     * @param SluggerInterface $slugger service injection
     * @return int 0 if able to auto fill the album, 1 if not
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function processLastFMRequestALbum(FormInterface $form,PictureUploader $pc,SluggerInterface $slugger): int
    {
        try{
            $entityManager = $this->getDoctrine()->getManager();
            $lf = new LastFmAPIController();
            $albumInfo = $lf->getAlbumInfo($form["name"]->getData(),$form["Artist"]->getData()->getName());
            $albumInfo = $albumInfo["album"];
            $tracksInfo  = $albumInfo["tracks"]["track"];
            $album = new Album();
            $album->setName($albumInfo["name"]);
            $album->setGenre($albumInfo["tags"]["tag"][0]["name"]);
            $album->setArtist($form["Artist"]->getData());
            $dateString = substr($albumInfo["wiki"]["published"], 0, strpos($albumInfo["wiki"]["published"], ','));
            $album->setReleaseDate(new \DateTime($dateString));
            $picturePath = $pc->getImageFromUrl($albumInfo["image"][4]["#text"]);//download the image and save to dir return the name
            $album->setPicture($picturePath);
            $entityManager->persist($album);
            $entityManager->flush($album);
            for($i = 0 ; $i < count($tracksInfo) ; $i++)
            {
                //create song object for each track and fill in the information
                $t =  new \DateTime(gmdate("H:i:s",$tracksInfo[$i]["duration"]));
                $t = $t->format("H:i:s");
                $t = substr($t,3);
                $song = new Tracks();
                $song->setDuration(new \DateTime($t));
                $song->setSongName($slugger->slug($slugger->slug($tracksInfo[$i]["name"])));
                $song->setAlbum($album);
                $entityManager->persist($song);
                $entityManager->flush($song);
            }
            return 0;
        }catch (\Exception $exception)
        {
            return 1;
        }
    }


    #[Route('/add/artist', name: 'app_music_add')]
    public function addArtist(Request $request,PictureUploader $pc):Response
    {
        $artist = new Artist();
        $form = $this->createForm(artistType::class,$artist);
        try {
            //$this->addEntityWithPicture($form,$artist,$pc, $request);
            $entityManager = $this->getDoctrine()->getManager();
            $form->handleRequest($request);
            if($form->isSubmitted() && $form->isValid())
            {
                $uploadedPicture = $form["picture"]->getData();
                $fileName = $pc->upload($uploadedPicture);//picture upload serv
                $artist->setPicture($fileName);
                $artist = $form->getData();
                $entityManager->persist($artist);
                $entityManager->flush($artist);
                return $this->redirectToRoute("app_home");

            }
        }catch(FileException $e)
        {
            return $this->render("exception.html.twig",["e"=>$e->getMessage()]);
        }
        return $this->renderForm('music/addArtist.html.twig', [
            "form" => $form,
        ]);
    }



    #[Route("/mod/submissions" ,name:"app_submission")]
    public function viewSubmission(ManagerRegistry $doctrine): Response
    {
        //need to send albums and their tracks
        $albums = $doctrine->getRepository(Album::class)->findBy(
            [
                "approved" => 0
            ]
        );
        $artist = $doctrine->getRepository(Artist::class)->findBy(
            [
                "approved" => 0
            ]
        );

        return $this->render("music/submission.html.twig",
        [
            "albums" => $albums,
            "artists" => $artist
        ]);
    }

    #[Route("/mod/submissions/accept/{id}" ,name:"app_accept_submission")]
    public function accept($id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        //dump($id);
        try {
            $album = $doctrine->getRepository(Album::class)->find($id);
            $this->acceptSubmission($album);
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",["e"=>$e]);
        }
        return $this->redirectToRoute("app_submission");//return the user back to the moderator page
    }

    #[Route("/mod/submissions/decline/{id}" ,name:"app_decline_submission")]
    public function declineAlbum($id, ManagerRegistry $doctrine)
    {
        $entityManager = $this->getDoctrine()->getManager();
        try {
            $album = $doctrine->getRepository(Album::class)->find($id);
            $this->declineSubmission($album);
            return $this->redirectToRoute("app_submission");
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",["e"=>$e->getMessage()]);
        }
    }


    //this is bad dont repeat code i fix if i have time later
    #[Route("/mod/submissions/artist/decline/{id}" ,name:"app_decline_artist")]
    public function declineArtist($id, ManagerRegistry $doctrine)
    {
        $entityManager = $this->getDoctrine()->getManager();
        try {
            $artist = $doctrine->getRepository(artist::class)->find($id);
            $this->declineSubmission($artist);
            return $this->redirectToRoute("app_submission");
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",["e"=>$e->getMessage()]);
        }
    }
    #[Route("/mod/submissions/artist/accept/{id}" ,name:"app_accept_artist")]
    public function acceptArtist($id, ManagerRegistry $doctrine): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        //dump($id);
        try {
            $artist = $doctrine->getRepository(artist::class)->find($id);
            $this->acceptSubmission($artist);

        }catch (EntityNotFoundException $e)
        {
            return new Response("adad");
        }
        return $this->redirectToRoute("app_submission");//return the user back to the moderator page
    }

    //dunno if this should go into the entity util service since its only being used by the music controller
    private function declineSubmission(SubmissionInterface $entity)
    {
        if($entity != null && $entity->isApproved() == false)
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($entity);
            $entityManager->flush();
        }else
        {
            throw new EntityNotFoundException("Could not decline artist with id: ".$id);
        }
    }

    private function acceptSubmission(SubmissionInterface $entity)
    {
        if($entity != null)
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entity->setApproved(true);
            $entityManager->persist($entity);
            $entityManager->flush($entity);

        }else
        {
            throw new EntityNotFoundException("Could not find album ");
        }
    }


    #[Route("/artist/{id}/{page}" ,name:"app_artist")]
    public function artistProfile(int $id, ManagerRegistry $doctrine,$page=1)
    {
        try {
            $artist = $doctrine->getRepository(Artist::class)->find($id);
            $albums = $doctrine->getRepository(Album::class)->getApproved($id);//need only approved albums
            //ump($albums);
            if($artist)
            {
                $lastFm = New LastFmAPIController();
                $data = $lastFm->getArtistsTopTracks($artist->getName());
                $trackData = $data["toptracks"]["track"];
                $totalPages = ceil( count($trackData)/10);
                $tracks = $this->paginate($trackData,$page);
                return $this->renderForm("music/artist.html.twig",
                    [
                        "artist" => $artist,
                        "albums" => $albums,
                        "tracks" => $tracks,
                        "totalPages" => $totalPages,
                        "page" => $page

                    ]
                );
            }else
            {
                throw new EntityNotFoundException("Could not find album with id: ".$id);
            }
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",["e"=>$e->getMessage()]);
        }
    }


    /**
     * paginates the track data so that only 10 tracks are shown at a time 
     * @param data track data
     * @param int page the page that they want to display
     * @return array array contains 10 tracks
     */
    private function paginate($data, int $page)
    {
        if($page == 1 || $page == 0)
        {
           $start =0;
        }else{
            $start = $page + 8;
        }
        return array_slice($data,$start,10);
    }



    #[Route("/album/{id}" ,name:"app_album")]
    public function getAlbum(int $id, Request $request, ManagerRegistry $doctrine)
    {
        /**
         * need to show the album
         * need to allow the user to write a review
         * need to allow the user to see reviews
         * moderators should be able to delete reviews
         */
        try {
            $album = $doctrine->getRepository(Album::class)->find($id);
            if($album) {
                $scores = $doctrine->getRepository(Review::class)->calculateScore($id);
                $averageScore = 0;
                if($scores)
                {
                    foreach($scores as $i)
                    {
                        $averageScore += $i["score"];
                    }
                    $averageScore = ceil($averageScore/count($scores));
                }
                $entityManager = $this->getDoctrine()->getManager();
                $review = new Review();
                $form = $this->createForm(ReviewType::class, $review);#
                $form->handleRequest($request);#
                if ($this->getUser())
                {
                    $userReview = $doctrine->getRepository(Review::class)
                        ->getUserAlbumReview
                        (
                            $album->getId(),
                            $this->getUser()->getId()
                        );//this is an array so it needs to be userReview.0.property
                }else
                {
                    $userReview = null;
                }
                if($form->isSubmitted() && $form->isValid())
                {

                    if($userReview)//removes the old review, could have just updated the field but cba doing that now
                    {
                        $doctrine->getRepository(Review::class)->remove($userReview[0]);
                    }
                    $review = $form->getData();
                    $review->setAlbum($album);
                    $review->setAuthor($this->getUser());
                    $entityManager->persist($review);
                    $entityManager->flush($review);
                    //quick refresh
                    $userReview = $doctrine->getRepository(Review::class)
                        ->getUserAlbumReview
                        (
                            $album->getId(),
                            $this->getUser()->getId()
                        );//keeps the old data for some reason even after a redirect so just update the value with the new one
                    $this->redirectToRoute("app_album",["id" =>$id]);
                }
                return $this->renderForm("music/album.html.twig",
                    [
                        "album" => $album,
                        "form" => $form,
                        "userReview" => $userReview,
                        "score" => $averageScore,
                    ]
                );
            }else
            {
                throw new EntityNotFoundException("Could not find album with id: ".$id);
            }
        }catch (EntityNotFoundException $e)
        {
            return new Response($e->getMessage());
        }
    }


    #[Route("/mod/remove/review/{id}" ,name:"app_remove_review")]
    public function removeReview(int $id, ManagerRegistry $doctrine): Response
    {
        try
        {
            $review = $doctrine->getRepository(Review::class)->find($id);
            if($review)
            {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($review);
                $entityManager->flush();
                return $this->redirectToRoute("app_home");
            }else{
                throw new EntityNotFoundException("a review with the id of ".$id. " does not exist");
            }
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",
                [
                    "e" => $e,
                ]);
        }
    }

    #[Route("/admin/remove/album/{id}" ,name:"app_remove_album")]
    public function removeAlbum(int $id,ManagerRegistry $doctrine, EntityUtil $entityUtil): Response
    {
        try {
            $album = $doctrine->getRepository(Album::class)->find($id);
            $entityUtil->deleteReviewHolder($album);
            return $this->redirectToRoute("app_home");
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",
                [
                    "e" => $e->getMessage(),
                ]);
        }
    }





}
