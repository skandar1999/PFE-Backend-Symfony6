<?php

namespace App\Controller;

use Symfony\Component\Mime\Email;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Util\SecureRandom;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

    
    
    class EmailController extends AbstractController
    {      #[Route('/reset-password', name: 'sendmail', methods:'POST')]
        public function sendEmail(MailerInterface $mailer,ManagerRegistry $doctrine,Request $request,UserPasswordHasherInterface $encoder,UserRepository $userRepository):  JsonResponse
        { 
            $data=json_decode($request->getContent(),true);
            $user = $userRepository->findOneBy(['email' => $data['email']]);

            if (!$user) {
                return $this->json(['error' => sprintf('User with username "%s" not found.',  $data['email'] )], 404);
            }
            $entityManager = $doctrine->getManager();

            $randomBytes = random_bytes(16);
            $password = bin2hex($randomBytes);
            
            $hashedPassword = $encoder->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $entityManager->flush();
            $email = (new Email())
                ->from('sabriskandar5@gmail.com')
                ->to($data['email'])
                ->subject('Reset Password email')
                ->text('Votre Nouvelle mot de passe est: '.$password);
    
            $mailer->send($email);
    
            return $this->json("Sent", 200);
        }
    }
    

