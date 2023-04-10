<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Entity\Dossier;

use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Exception\InvalidArgumentException;


class DossierController extends AbstractController
{
    #[Route('/createdossier/{email}', name: 'dossier-create', methods:'POST')]
    public function new(string $email,Request $request, EntityManagerInterface $entityManager)
{
    $filesystem = new Filesystem();
    $user_id = $request->request->get('user_id');
    $namedossier = $request->request->get('namedossier', 'New Folder');
    $datedossier = $request->request->get('datedossier', date('Y-m-d')); // Get the date from the request body, or use today's date if not provided

    // Get the User object associated with the user_id value
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    // Créez le chemin vers le dossier
    $chemin_dossier = $this->getParameter('folder_directory') . "/{$user_id}_{$namedossier}_{$datedossier}";

    // Créez le dossier
    $dossier = new Dossier();
    $dossier->setUser($user); // Set the User object
    $dossier->setNamedossier($namedossier);
    $dossier->setDatedossier(new \DateTime($datedossier)); // Assuming $datedossier is a valid date string

    // Persist the Dossier object to the database
    $entityManager->persist($dossier);
    $entityManager->flush();

    // Return a response indicating success
    return new Response('Folder created successfully');

    
}


#[Route('/getfolder/{email}', name: 'get_folder_files', methods: ['GET'])]
public function getUserFiles(string $email, EntityManagerInterface $entityManager): JsonResponse
{
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    $dossiers = $entityManager->getRepository(Dossier::class)->findBy(['user' => $user], ['datedossier' => 'DESC']);

    $responseData = [];

    foreach ($dossiers as $dossier) {
        
        $responseData[] = [
            'id' => $dossier->getId(),
            'user_id' => $dossier->getUser()->getId(),
            'name' => $dossier->getNamedossier(),
            'date' => $dossier->getDatedossier()->format('Y-m-d'),
        ];
    }

    return new JsonResponse($responseData);
}





#[Route('/deletefolder/{id}', name: 'delete_folder', methods: ['DELETE'])]

public function deleteFile(int $id, EntityManagerInterface $entityManager): JsonResponse
{
    $folder = $entityManager->getRepository(Dossier::class)->find($id);

    if (!$folder) {
        throw $this->createNotFoundException('folder not found');
    }

    // Remove the file from the server
    $foldername = $folder->getNamedossier();
    if (file_exists($foldername)) {
        unlink($foldername);
    }

    // Remove the file from the database
    $entityManager->remove($folder);
    $entityManager->flush();

    return new JsonResponse(['message' => 'Folder deleted successfully']);
}


/*
#[Route("/folders/{id}", name: "get_files_by_folder_and_file",methods: ['GET'])]
public function getFilesByFolderAndFile($id, $file_id, FileRepository $fileRepository): JsonResponse
{
    $files = $fileRepository->findBy(['folder' => $id, 'id' => $file_id]);
    // rest of the code to return the file(s)
    return new JsonResponse([]);

}
*/



#[Route('/rename_folder/{id}', name: 'rename_folder', methods: ['POST'])]
public function renameFolder(Request $request, EntityManagerInterface $entityManager, int $id): Response
{
    $dossier = $entityManager->getRepository(Dossier::class)->find($id);
    if (!$dossier) {
        throw $this->createNotFoundException('Dossier not found');
    }

    // Get the new name from the request
    $newName = $request->request->get('name');

    if (!$newName) {
        throw $this->createNotFoundException('New name not specified');
    }

    // Set the new name of the Dossier entity
    $dossier->setNamedossier($newName);

    // Save the changes to the database
    $entityManager->flush();

    return new Response('Folder renamed successfully.');
}


#[Route('/dossiers/{id}/files', name: 'get_files_by_folder', methods: ['GET'])]
public function getFilesByFolder(Dossier $folder): Response
{
    $files = $folder->getFiles();
    $data = [];

    foreach ($files as $file) {
        $data[] = [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
            'date' => $file->getDate()->format('Y-m-d'),
            'status' => $file->getStatus(),
            'user' => [
                'id' => $file->getUser()->getId(),
                'name' => $file->getUser()->getName(),
            ],
            'folder' => [
                'id' => $file->getFolder()->getId(),
                'name' => $file->getFolder()->getName(),
            ],
        ];
    }

    return new JsonResponse($data);
}


    // uploade file in dosssier
    #[Route('/dossiers/{id}', name: 'add_file_to_dossier', methods: ['POST'])]
    public function addFileToDossier(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Load the dossier by ID
        $dossier = $entityManager->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            throw $this->createNotFoundException('Dossier not found');
        }
    
        $uploadedFile = $request->files->get('file');
        $name = $request->request->get('name') ?? $uploadedFile->getClientOriginalName();
    
        // Check if a file with the given name already exists in the database for this dossier
        $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'dossier' => $dossier], ['version' => 'DESC']);
        if ($existingFiles) {
            $version = $existingFiles[0]->getVersion() + 1;
        } else {
            $version = 1;
        }
    
        // Append the version number to the file name
        $originalName = $uploadedFile->getClientOriginalName();
        $parts = pathinfo($originalName);
        $extension = isset($parts['extension']) ? '.' . $parts['extension'] : '';
        $basename = basename($originalName, $extension);
    
        $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $originalName, 'dossier' => $dossier]);
        if ($existingFiles) {
            $i = 2;
            do {
                $name = $basename . '_' . $i . $extension;
                $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'dossier' => $dossier]);
                $i++;
            } while ($existingFiles);
            $version = $i - 1;
        } else {
            $name = $originalName;
        }
    
        // Create a new File entity and set its properties
        $file = new File();
        $file->setName($name);
        $file->setDate(new \DateTime());
        $file->setUser($this->getUser());
        $file->setDossier($dossier); // Set the dossier of the file
        $file->setVersion($version);
    
        // Move the uploaded file to a directory on the server
        $uploadDir = $this->getParameter('uploads_directory');
        $uploadedFile->move($uploadDir, $name);
        $file->setPath($uploadDir.'/'.$name);
    
        // Persist the File entity to the database
        $entityManager->persist($file);
        $entityManager->flush();
    
        return new Response('File uploaded successfully.');
    }
    


    #[Route('/FilesByDossiers/{id}', name: 'FilesByDossier', methods: ['GET'])]
    public function getFilesByDossier(int $id, FileRepository $fileRepository): JsonResponse
{
    $files = $fileRepository->findBy(['dossier' => $id], ['date' => 'DESC']);

    if (!$files) {
        return new JsonResponse(['error' => 'No files found for dossier'], Response::HTTP_NOT_FOUND);
    }

    $responseData = [];
    foreach ($files as $file) {
        $responseData[] = [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
            'date' => $file->getDate()->format('Y-m-d'),
            'status' => $file->getStatus(),
            // add any other properties you want to return
        ];
    }

    return new JsonResponse($responseData);
}
    




//get dosssier name
#[Route("/dossiersname/{id}", name:"get_dossier", methods:["GET"])]
public function getDossierName(int $id, DossierRepository $dossierRepository): JsonResponse
{
   $dossier = $dossierRepository->find($id);
   if (!$dossier) {
       return new JsonResponse(['error' => 'Dossier not found'], Response::HTTP_NOT_FOUND);
   }

   $data = ['name' => $dossier->getNamedossier(),
            'id' => $dossier->getId()];
   return new JsonResponse($data);
}


        // update file statut dans dossier
        #[Route("/filesss/{id}", name:"update_file_status", methods:["PUT"])]
        public function updateFileStatus(int $id, EntityManagerInterface $entityManager, Security $security): JsonResponse
        {
            $file = $entityManager->getRepository(File::class)->find($id);
        
            if (!$file) {
                throw $this->createNotFoundException('File not found');
            }
        
            $loggedInUser = $security->getUser();
            $dossier = $file->getDossier();
        
            if ($dossier && $dossier->getUser()) {
                $file->setUser($dossier->getUser()); // Set the user of the file to the owner of the dossier
                $file->setStatus(false);
                $entityManager->flush();
        
                return new JsonResponse(['message' => 'File status updated successfully', 'dossier' => $dossier], 200);
            } else {
                return new JsonResponse(['error' => 'You are not authorized to update this file'], 403);
            }
        }
        
        
        

        

        
             






#[Route("/FilesByDossiersUser/{id}", name:"get_files_byDossieranduser", methods:["GET"])]
public function getFilesByFolderAndUser(int $id, string $email, DossierRepository $dossierRepository, UserRepository $userRepository): JsonResponse
{
    $dossier = $dossierRepository->find($id);
    if (!$dossier) {
        return new JsonResponse(['error' => 'Dossier not found'], Response::HTTP_NOT_FOUND);
    }
    
    $user = $userRepository->findOneBy(['email' => $email]);
    if (!$user) {
        return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
    }
    
    // retrieve the dossier's files
    $files = [];
    foreach ($dossier->getFiles() as $file) {
        if ($file->getUser() === $user) {
        $files[] = [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
            'status' =>$file->getStatus(),
            // add any other properties you want to return
        ];}
    }

    // return the dossier data as JSON
        $data = [
            'id' => $dossier->getId(),
            'files' => $files,
            
            // add any other properties you want to return
        ];
        return new JsonResponse($data);
    }



}
