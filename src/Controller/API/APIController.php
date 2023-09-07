<?php

namespace App\Controller\API;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use App\Form\ReviewAPIType;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

use JMS\Serializer\SerializationContext;

/**
 * needs to list, create, edit and delete album reviews
 * use o auth for this, for verfication for the api requests
 *
 */


class APIController extends AbstractFOSRestController
{


    #[Get("/api/v1/albums", name:"api_albums")]
    /**
     * returns a json of a album's details
     * @param ManagerRegistry $doctrine, autowiring
     * @return Response, json of the album's details
     */
    public function getAlbums(ManagerRegistry $doctrine,): Response
    {

        try {
            $data = $doctrine->getRepository(Album::class)->findBy(["approved" => 1]);

            return $this->handleView($this->view(
                [
                    "albums" => $data,

                ]
                , 200
            ));
        }catch (\Exception $e){
                return $this->handleView($this->view($e->getMessage(),404));
            }

    }

    #[Get("/api/v1/albums/{albumID}", name:"api_album")]
    /**
     * returns a json of a album's details
     * @param int $albumID, id of the album you want to search for
     * @param ManagerRegistry $doctrine, autowiring
     * @return Response, json of the album's details
     */
    public function getAlbum(int $albumID, ManagerRegistry $doctrine): Response
    {
        $data = $doctrine->getRepository(Album::class)->find($albumID);
        if($data==null)
        {
            return $this->handleView($this->view(
                ["error message" => "cannot find album with provided details"], 404
            ));
        }else if($data->isApproved() == false)
        {
            return $this->handleView($this->view(
                ["error message" => "album not approved"], 403
            ));
        }else{
            return $this->handleView($this->view(
                ["reviews" => $data], 200
            ));
        }
    }

    #[Get("/api/v1/albums/{albumID}/reviews", name:"api_album_reviews")]
    /**
     * returns a list of all the reviews of an album
     * @param int $albumID id of the album you want to search for
     * @param ManagerRegistry $doctrine
     * @return Response, returns json
     */
    public function getAlbumReviews(int $albumID,ManagerRegistry $doctrine): Response
    {
        $album = $doctrine->getRepository(Album::class)->find($albumID);
        if($album != null)
        {
            $reviews = $album->getReviews();
            if($album->isApproved()) {
                return $this->handleView($this->view(
                    [
                        "user reviews" => $reviews,
                    ],
                    200
                ));
            }else{
                return $this->handleView($this->view(
                    ["error message: " =>"album hasn't been approved"],
                    403
                ));
            }

        }else{
            return $this->handleView($this->view(
                ["error message: " =>"unable to find album"],
                404
            ));
        }
    }




    #[Post("api/v1/albums/{albumID}/reviews/users/{userID}", name: "api_album_post_review"),]
    /**
     * rest api request to create an review. It won't create a review if a user already has a review under the
     * album.
     * @param int $albumID id of the album
     * @param string $userID id of the user
     * @param Request $request
     * @param ManagerRegistry $doctrine
     * @return Response json
     */
    public function addReview(int $albumID,string $userID, Request $request, ManagerRegistry $doctrine,Security $security): Response
    {
        $user = $security->getUser();
        $author = $doctrine->getRepository(User::class)->find($userID);
        $authorised = $this->authoriseUser($user,$author);
        if($authorised)
        {
            try
            {

                $reviewForm = $this->createForm(ReviewAPIType::class);
                $data = json_decode($request->getContent(),true);
                $reviewForm->submit($data);
                if($reviewForm->isValid())
                {

                    $album = $doctrine->getRepository(Album::class)->find($albumID);
                    if(!$album->isApproved()) {
                        return $this->handleView($this->view(
                            ["error message: " =>"album hasn't been approved"],
                            403
                        ));
                    }
                    if($album == null)
                    {
                        $returnMessage = ["errorMessage" => "unable to find album"];
                        $code = 404;
                    }elseif ($author == null)
                    {
                        $returnMessage = ["errorMessage" => "unable to find user"];
                        $code = 404;
                    }else{
                        $result = $doctrine->getRepository(Review::class)->getUserAlbumReview($albumID,$author->getID());
                        if(count($result) > 0)//review already exists
                        {
                            //https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/409
                            //https://www.restapitutorial.com/lessons/httpmethods.html
                            $returnMessage = [
                                "review" => "http://127.0.0.1:8000/album/$albumID",
                                "previous review" => $result[0]
                            ];
                            $code = 409;
                        }else{
                            $review = $reviewForm->getData();
                            $review->setAlbum($album);
                            $review->setAuthor($author);
                            $em = $this->getDoctrine()->getManager();
                            $em->persist($review);
                            $em->flush($review);
                            $returnMessage  = [
                                "review:" =>"http://127.0.0.1:8000/album/$albumID"
                            ];
                            $code = 201;
                        }
                    }
                }else {
                    $returnMessage = ["error message" => "data entered is not valid"];
                    $code = 400;
                }

            }catch(\Exception $e)
            {
                $message = $e->getMessage();
                return $this->handleView($this->view(
                    ["errorMessage:" =>"$message"],
                    400
                ));
            }
        }else{
            $returnMessage = ["error message" => "you're not authorised to create a review for another user",$user->getRoles(),"f"=>$authorised];
            $code = 401;
        }
        return $this->handleView($this->view(
            $returnMessage,
            $code
        ));

    }



    #[Delete("api/v1/albums/{albumID}/reviews/users/{userID}", name:"api_album_review_delete")]
    /**
     * rest api request to delete a user's review under a specific album
     * @param int $albumID id of the album
     * @param int $userID id of the user
     * @param ManagerRegistry $doctrine
     * @return Response 200 = successful, 404 = unable to find the review
     */
    public function deleteReview(int $albumID,int $userID,ManagerRegistry $doctrine,Security $security)
    {
        $user = $security->getUser();
        $targetUser = $doctrine->getRepository(User::class)->find($userID);
        $authorised  = $this->authoriseUser($user,$targetUser,true );
        if($authorised)
        {
            $review = $doctrine->getRepository(Review::class)->getUserAlbumReview($albumID,$targetUser->getId());
            if(empty($review))
            {
                $message = ["errorMessage" =>"could not find review with the details provided"];
                $code=404;
            }else{
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($review[0]);
                $entityManager->flush();
                $message = ["message" =>"successfully removed the review $userID under album id:$albumID"];
                $code=200;
            }

        }else
        {
            $message = ["message" =>"you're not authorised to delete someone else's review"];
            $code=401;
        }
        return $this->handleView($this->view(
            $message,
            $code
        ));

    }

    #[Put("api/v1/albums/{albumID}/reviews/users/{userID}", name:"api_album_edit_review")]
    /**
     * rest api request to edit a user's review under album
     * @param int $albumID id of the album
     * @param int $userID id of the user
     * @param ManagerRegistry $doctrine
     * @param Request $request
     * @return Response
     */
    public function editReview(int $albumID,int $userID,ManagerRegistry $doctrine, Request $request,Security $security): Response
    {
        $data = json_decode($request->getContent(),true);
        $user = $security->getUser();
        $targetUser = $doctrine->getRepository(User::class)->find($userID);
        $authorised  = $this->authoriseUser($user,$targetUser,);
        if($authorised)
        {
            try {
                //for consistancy
                $author = $doctrine->getRepository(User::class)->find($targetUser->getId());
                $album = $doctrine->getRepository(Album::class)->find($albumID);
                if($album == null)
                {
                    $message = ["errorMessage" => "unable to find album"];
                    $code = 404;
                }elseif ($author == null) {
                    $message = ["errorMessage" => "unable to find user"];
                    $code = 404;
                }else{
                    $review = $doctrine->getRepository(Review::class)->getUserAlbumReview($albumID,$author->getID());
                    if(empty($review))
                    {
                        $message = ["errorMessage" => "unable to find review"];
                        $code = 404;
                    }else
                    {
                        $reviewForm = $this->createForm(ReviewAPIType::class);
                        $reviewForm->submit($data);
                        if($reviewForm->isValid()){
                            if(strlen($data["text"])> 255)//hardcoded
                            {
                                throw  new  \Exception("text is too many characters");
                            }
                            $review = $review[0];
                            $review->setAlbum($album);
                            $review->setAuthor($author);

                            $review->setText($data["text"]);
                            $review->setScore($data["score"]);
                            $em = $this->getDoctrine()->getManager();
                            $em->merge($review);
                            $em->flush();
                            $message  = [
                                "review:" =>"http://127.0.0.1:8000/album/$albumID"
                            ];
                            $code = 200;
                        }else{
                            $message = ["errorMessage" => "invalid data"];
                            $code = 400;
                        }
                    }
                }

            }catch(\Exception $e){
                $message = $e->getMessage();
                return $this->handleView($this->view(
                    ["errorMessage:" =>"invalid data: $message"],
                    400
                ));
            }
        }else{
            $message = ["message" =>"you're not authorised to edit someone else's review"];
            $code = 401;
        }
        return $this->handleView($this->view(
            $message,
            $code
        ));
    }


    private function authoriseUser($user,$target=null,$security=false):bool
    {
        //check if the user is admin or mod
        //check if the user the is the target user
        if($user === $target){
            return True;
        }else
        {
            if($security==true)//only admin
            {
                return in_array("ROLE_ADMIN",$user->getRoles());
            }else//mod
            {
                if(in_array("ROLE_MOD",$user->getRoles()) or in_array("ROLE_ADMIN",$user->getRoles()))
                {

                    return True;
                }
                else
                {
                    return False;
                }
            }
        }
    }







}
