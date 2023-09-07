<?php

namespace App\Controller;

use App\Form\LoginType;
use App\Service\EntityUtil;
use App\Service\PictureUploader;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use http\Exception\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use App\Form\UserType;

class AccountController extends AbstractController
{
    #[Route('/account/register/{error}', name: 'app_account_register')]
    public function register(UserPasswordHasherInterface $passwordHasher, Request $request,PictureUploader $pc,int$error=0): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class,$user);
        $form->handleRequest($request);
        $entityManager = $this->getDoctrine()->getManager();
        if($form->isSubmitted() && $form->isValid()) {
            $unHashedP = $form["password"]->getData();
            $hashedP = $passwordHasher->hashPassword($user, $unHashedP);
            $user->setPassword($hashedP);
            $uploadedPicture = $form["picture"]->getData();
            if ($uploadedPicture)//checking if the user has uploaded a picture
            {
                try {
                    $fileName = $pc->upload($uploadedPicture);//picture upload service
                    $user->setPicture($fileName);
                    $user = $form->getData();
                    $entityManager->persist($user);
                    $entityManager->flush($user);
                    $this->redirectToRoute("app_account_login");
                } catch (FileException $e) {
                    return $this->redirectToRoute("app_account_register",["error" =>2]);
                }catch(Exception\UniqueConstraintViolationException)
                {
                    return $this->redirectToRoute("app_account_register",["error" =>1]);
                }
            }
        }
        return $this->renderForm('account/register.html.twig', [
            "form" => $form,
            "uName" => false,
            "error" => $error

        ]);
    }


    #[Route('/account/login', name: 'app_account_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render(
            "account/login.html.twig",
            [
                "lastUsername" =>$lastUsername,
                "error" => $error,
            ]
        );
    }


    #[Route('/account/logout', name: 'app_account_logout')]
    public function logout(): Response
    {
        //the body shouldn't run
        throw new \Exception("Something went horribly wrong, check security.yaml");
    }


    #[Route('/account/profile/{username}', name: 'app_account_profile')]
    public function profile(string $username, ManagerRegistry $doctrine ):Response
    {
        $user = $doctrine->getRepository(User::class)->findOneBy(["username" => $username]);
        $reviews = $user->getReviews();

        if($reviews->isEmpty() === true)//might need to change this later need to make a review first
        {
            $reviews = null;
        }
        return $this->render(
            "account/profile.html.twig",
            [
                "user" => $user,
                "reviews" => $reviews,
            ]
        );
    }

    #[Route("/admin/makeMod/{id}", name: "app_account_make_mod")]
    public function makeModerator(int $id,ManagerRegistry $doctrine): Response
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        //dump($user);
        try {
            if($user)
            {
                $this->setRole("ROLE_MOD",$user);
                return $this->redirectToRoute("app_account_profile",["username" => $user->getUsername()]);

            }else
            {
                throw new EntityNotFoundException("a user with the id of ".$id. " does not exist");
            }
        }catch (\Exception $e)
        {
            return $this->render("exception.html.twig",
            [
                "e" => $e,
            ]);
        }
    }

    #[Route("/admin/removeMod/{id}", name: "app_account_remove_mod")]
    public function removeModerator(int $id,ManagerRegistry $doctrine): Response
    {
        $user = $doctrine->getRepository(User::class)->find($id);
        try {
            if($user)
            {
                $this->setRole("",$user);
                return $this->redirectToRoute("app_account_profile",["username" => $user->getUsername()]);

            }else
            {
                throw new EntityNotFoundException("a user with the id of ".$id. " does not exist");
            }
        }catch (\Exception $e)
        {
            return $this->render("exception.html.twig",
                [
                    "e" => $e,
                ]);
        }
    }

    private function setRole(string $role,User $user)
    {
        $entityManager = $this->getDoctrine()->getManager();
        switch($role)
        {
            case "ROLE_MOD":
                $user->setRoles(["ROLE_MOD"]);
                $entityManager->persist($user);
                $entityManager->flush($user);
                break;
            case "":
                $user->setRoles([]);
                $entityManager->persist($user);
                $entityManager->flush($user);
                break;
            default:
                throw new InvalidArgumentException($role." isn't a valid role");
        }
    }

    #[Route("/admin/delete/{id}", name: "app_account_delete")]
    public function deleteUser(int $id,ManagerRegistry $doctrine, EntityUtil $entityUtil)
    {
        //need to delete the user and their reviews
        try{
            $user = $doctrine->getRepository(User::class)->find($id);
            $entityUtil->deleteReviewHolder($user);
            return $this->redirectToRoute("app_home");
        }catch (EntityNotFoundException $e)
        {
            return $this->render("exception.html.twig",
                [
                    "e" => $e,
                ]);
        }
    }

}
