<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Entity\Dossier;

use App\Repository\FileRepository;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


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



}