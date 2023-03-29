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
                return $this->json(['error' => sprintf('Adresse email n\'existe pas.',  $data['email'] )], 404);
            }
            $entityManager = $doctrine->getManager();

            $randomBytes = random_bytes(5);
            $password = bin2hex($randomBytes);
            
            $hashedPassword = $encoder->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $entityManager->flush();
            $email = (new Email())
                ->from('sabriskandar5@gmail.com')
                ->to($data['email'])
                ->subject('Réinitialisation du mot de passe')
                ->html('<p>Bonjour,</p>
                        <p>Nous avons récemment mis à jour nos systèmes de sécurité et, pour garantir la sécurité de votre compte, nous avons généré un nouveau mot de passe pour vous.</p>
                        <p>Votre nouveau mot de passe est : <strong>' . $password . '</strong></p>
                        <p>Veuillez noter que ce mot de passe est sensible à la casse et doit être saisi exactement tel qu\'il apparaît ci-dessus lors de votre prochaine connexion à notre service. Nous vous recommandons également de changer ce mot de passe dès que possible pour un mot de passe que vous seul connaissez.</p>
                        <p>Cordialement,<br>L\'équipe de sécurité</p><a href="http://localhost:4200/login" style="display:inline-block; background-color: #fb5c42; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Aller sur notre site web</a>
                        ');
            
            $mailer->send($email);
    
            return $this->json("Sent", 200);
        }
    }
    

