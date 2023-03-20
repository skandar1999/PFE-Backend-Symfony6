<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;



class UserController extends AbstractController
{
    private $manager;
    private $user;


    public function __construct(EntityManagerInterface $manager, UserRepository $user)
    {
         $this->manager=$manager;

         $this->user=$user;
    }
    
    
    //Création d'un utilisateur
    #[Route('/userCreate', name: 'user_create', methods:'POST')]
    public function userCreate(Request $request): Response
    {



       $data=json_decode($request->getContent(),true);


       $email=$data['email'];
       $username=$data['username'];
       $password=$data['password'];
       $mobile=$data['mobile'];
      

       //Vérifier si l'email existe déjà

       $email_exist=$this->user->findOneByEmail($email);

       if($email_exist)
       {
          return new JsonResponse
          (
              [
                'status'=>false,
                'message'=>'Email existe déjà, veuillez le changer'
              ]

              );
       }    

       $username_exist=$this->user->findOneByUsername($username);

   if($username_exist)
   {
      return new JsonResponse
      (
          [
            'status'=>false,
            'message'=>'Nom utlilisateur existe déjà, veuillez le changer'
          ]
      );
   }

       else
       {
          $user= new User();

          $user->setEmail($email)
                ->setUsername($username)
                ->setMobile($mobile)
                ->setPassword(sha1($password))
                ->setRoles(['ROLE_USER']);

         $this->manager->persist($user);
         $this->manager->flush();

         return new JsonResponse
         (
             [
               'status'=>true,
               'message'=>'user créé avec succès'
             ]

             );
       }
    }

     //Liste des utilisateurs
    #[Route('/getAllUsers', name: 'get_allusers', methods:'GET')]
    public function getAllUsers(): Response
    {
        $users=$this->user->findAll();

        return $this->json($users,200);
    }

    #[Route('/delete/{id}', name: 'delete_create', methods:'DELETE')]
    public function delete(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);
   
        if (!$user) {
            return $this->json('No user found for id' . $id, 404);
        }
   
        $entityManager->remove($user);
        $entityManager->flush();
   
        return $this->json('Deleted a user successfully with id ' . $id);
    }

     //find  des users par username
#[Route('/findByUsername/{username}', name: 'getuser', methods: ['GET'])]
public function findUser2(string $username, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['username' => $username]);

    if (!$user) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $username )], 404);
    }
    return $this->json([
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'email' => $user->getEmail(),
        'roles' => $user->getRoles(),
        
    ]);
}

     //find  des users par email
#[Route('/findByEmail/{email}', name: 'getuser_email', methods: ['GET'])]
public function findUser(string $email, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['email' => $email]);

    if (!$user) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $email )], 404);
    }
    return $this->json([
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'email' => $user->getEmail(),
        'roles' => $user->getRoles(),
        'mobile' => $user->getMobile(),
        'image' => $user->getImage(),

    ]);
}


     //find  des users par id
#[Route('/findById/{id}', name: 'getuser', methods: ['GET'])]
public function findUserById(int $id, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['id' => $id]);

    if (!$user) {
        return new JsonResponse(['error' => sprintf('User with id "%s" not found.', $id)], 404);
    }

    return new JsonResponse([
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'email' => $user->getEmail(),
        'roles' => $user->getRoles(),
        'password' => $user->getPassword(),
    ]);
}

     //update des users profile
#[Route('/updateUser/{email}', name: 'user_update', methods: ['PUT', 'PATCH'])]
public function update(Request $request, ManagerRegistry $doctrine, string $email ,UserPasswordHasherInterface $encoder ): Response
{
    $entityManager = $doctrine->getManager();
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user) {
        return $this->json(['message' => 'User not found'], 404);
    }

    $data = json_decode($request->getContent(), true);

    if (isset($data['email'])) {
        $user->setEmail($data['email']);
    }

    if (isset($data['username'])) {
        $user->setUsername($data['username']);
    }
    if (isset($data['mobile'])) {
        $user->setMobile($data['mobile']);
    }
    if (isset($data['password'])) {
        $user->setPassword(sha1($data['password']));
    }

    
    $entityManager->flush();

    return $this->json(['message' => 'User updated with success', 'data' => [
        'id' => $user->getId(),
        'email' => $user->getEmail(),
        'username' => $user->getUsername(),
        'mobile' => $user->getMobile(),
        'password' => $user->getpassword(),
        'roles' => $user->getRoles(),

    ]]);
}


     //update les roles comme admin
#[Route('/updateRole/{id}', name: 'Roles-update', methods: ['PUT', 'PATCH'])]
public function updateRoles(Request $request, ManagerRegistry $doctrine, int $id): Response
{
    $entityManager = $doctrine->getManager();
    $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id]);
    
    if (!$user) {
        return $this->json(['message' => 'User not found'], 404);
    }
    
    $user->setRoles(['ADMIN']);
    $entityManager->flush();

    return $this->json([
        'message' => 'User updated with success',
        'data' => [
            'roles' => $user->getRoles(),
        ]
    ]);
}

     //annuler le role admin 
     #[Route('/removeRole/{id}', name: 'Roles-remove', methods: ['PUT', 'PATCH'])]
public function removeRole(Request $request, ManagerRegistry $doctrine, int $id): Response
{
    $entityManager = $doctrine->getManager();
    $user = $entityManager->getRepository(User::class)->findOneBy(['id' => $id]);

    if (!$user) {
        return $this->json(['message' => 'User not found'], 404);
    }

    $roles = $user->getRoles();
    if (in_array('ADMIN', $roles)) {
        $key = array_search('ROLE_ADMIN', $roles);
        unset($roles[$key]);
    }
    
    // Filter the roles to only include 'ROLE_USER'
    $roles = ['ROLE_USER'];
    
    $user->setRoles($roles);
    $entityManager->flush();

    return $this->json([
        'message' => 'User updated with success',
        'data' => [
            'roles' => $user->getRoles(true),
        ]
    ]);
}







/*
#[Route('/users/{email}/image', name: 'update_user_image', methods: ['PUT', 'PATCH'])]
public function updateUserImage(Request $request, EntityManagerInterface $entityManager, string $email, FileUploader $fileUploader): JsonResponse
{
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    $imageFile = $request->files->get('image');

    if (!$imageFile) {
        throw new BadRequestHttpException('No image uploaded');
    }

    $fileName = $fileUploader->upload($imageFile);
    $user->setImage($fileName);

    $entityManager->flush();

    return new JsonResponse(['message' => 'Image uploaded successfully']);
}


*/
#[Route('/users/{id}/image', name: 'update_user_image', methods: ['PUT', 'PATCH'])]
public function uploadImage(int $id, Request $request, EntityManagerInterface $entityManager): Response
{
    // Find the user you want to update
    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    // Get the uploaded image file from the request
    $imageFile = $request->files->get('image');

    if (!$imageFile) {
        throw $this->createNotFoundException('Image not found in request');
    }

    // Set the user's new image
    $user->setImage($imageFile->getClientOriginalName());

    // Move the uploaded file to the public/uploads directory
    $imageFile->move(
        $this->getParameter('images_directory'),
        $imageFile->getClientOriginalName()
    );

    // Save the changes to the database
    $entityManager->flush();

    return new Response('Image uploaded successfully', Response::HTTP_OK);
}








}