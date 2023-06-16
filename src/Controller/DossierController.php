<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Entity\Dossier;

use App\Entity\SousDossier;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SousDossierRepository;
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
            'status'=> $dossier->getStatus(),

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
            'size'=> $file->getSize(),

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


    


    #[Route('/checkfile/{id}', name: 'checkindossier', methods: ['POST'])]
    public function checkFileExists(int $id, Request $request, EntityManagerInterface $entityManager)
    {
        $dossier = $entityManager->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            throw $this->createNotFoundException('Dossier not found');
        }
    
        $uploadedFile = $request->files->get('file');
        $name = $request->request->get('name') ?? $uploadedFile->getClientOriginalName();
    
        $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'dossier' => $dossier], ['version' => 'DESC']);
        $fileExists = !empty($existingFiles);
    
        return new JsonResponse(['exists' => $fileExists]);
    }



    #[Route('/checkfolder/{email}', name: 'check', methods: ['POST'])]
    public function checkFolderExists(string $email, Request $request, EntityManagerInterface $entityManager)
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
    
        $name = $request->request->get('name');
    
        $existingFolder = $entityManager->getRepository(Dossier::class)->findOneBy(['namedossier' => $name, 'user' => $user]);
        $folderExists = $existingFolder !== null;
    
        return new JsonResponse(['exists' => $folderExists]);
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
        $size = $uploadedFile->getSize();
    
        $file = new File();
        $file->setName($name);
        $file->setSize($size);
        $sizeHumanReadable = $file->getSizeHumanReadable();
        // Check if a file with the given name already exists in the database for this dossier
        $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'dossier' => $dossier], ['version' => 'DESC']);
        if ($existingFiles) {
            $version = $existingFiles[0]->getVersion() + 1;
            $codefile = $existingFiles[0]->getCodefile();
            $existe = true;
        } else {
            $version = 1;
            $randomBytes = random_int(1000, 9999);
            $codefile = bin2hex($randomBytes);
            $existe = false;
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
                $name = $basename . ' (' . $i . ')' . $extension;
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
        $file->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
        $file->setUser($this->getUser());
        $file->setDossier($dossier); // Set the dossier of the file
        $file->setVersion($version);
        $file->setCodefile(strval($codefile));
    
        $file->setSize($sizeHumanReadable);
    
        // Move the uploaded file to a directory on the server
        $uploadDir = $this->getParameter('uploads_directory');
        $uploadedFile->move($uploadDir, $name);
        $file->setPath($uploadDir.'/'.$name);
    
        // Persist the File entity to the database
        $entityManager->persist($file);
        $entityManager->flush();
    
        $responseData = [
            'message' => 'File uploaded successfully.',
            'existe' => $existe,
        ];
    
        return new Response(json_encode($responseData));

    }

    


    //to correctly delete the files with the same code file except for the one with the latest date,
    //you need to adjust the code by first retrieving all files with the same code file, and then comparing their
    // dates. Here's the modified code:

       



        // FileuploadAndReplace as dossierID
        #[Route('/FileuploadAndReplace/{id}', name: 'Fileuploadever_filee', methods: ['POST'])]
        public function uploadFilever(int $id, Request $request, EntityManagerInterface $entityManager, Filesystem $filesystem): Response
        {
            // Load the dossier by ID
            $dossier = $entityManager->getRepository(Dossier::class)->find($id);
    if (!$dossier) {
        throw $this->createNotFoundException('Dossier not found');
    }

    // Get the uploaded file and its properties
    $uploadedFile = $request->files->get('files');
    $name = $request->request->get('name') ?? $uploadedFile->getClientOriginalName();
    $size = $uploadedFile->getSize();

    // Check if a file with the given name already exists in the database for this dossier
    $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'dossier' => $dossier]);
    $codefile = null;
    if ($existingFiles) {
        $existingFile = $existingFiles[0];
        $codefile = $existingFile->getCodefile();

        // Delete the existing file
        $path = $existingFile->getPath();
        $filesystem->remove($path);
        $entityManager->remove($existingFile);
    }

    // Create a new File entity and set its properties
    $file = new File();
    $file->setName($name);
    $file->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
    $file->setDossier($dossier);
    $file->setVersion(1);
    $file->setSize($size);
    $sizeHumanReadable = $file->getSizeHumanReadable();
    $file->setSize($sizeHumanReadable);
    $file->setCodefile($codefile);

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
        $name = $file->getName();
        
        
        $responseData[] = [
            'id' => $file->getId(),
            'name' => $name,
            'path' => $file->getPath(),
            'date' => $file->getDate() ? $file->getDate()->format('d-m-y H:i') : null,
            'status' => $file->getStatus(),
            'size'=> $file->getSize(),
            'codefile'=> $file-> getCodefile()
        ];
    }
    
    
    return new JsonResponse($responseData);
}


/*
//DELETE files whose the same codefile
#[Route('/DeletefilesSamecode/{id}', name: 'DELETEFilesByDossiercode1', methods: ['POST'])]
public function deleteFilesByDossierr(int $id, FileRepository $fileRepository, EntityManagerInterface $entityManager): JsonResponse
{
    $files = $fileRepository->findBy(['dossier' => $id], ['date' => 'DESC']);

    if (!$files) {
        return new JsonResponse(['error' => 'No files found for dossier'], Response::HTTP_NOT_FOUND);
    }

    $codefileCounts = [];
    $responseData = [];

    foreach ($files as $file) {
        $codefile = $file->getCodefile();

        // Check if the codefile already exists in the counts array
        if (isset($codefileCounts[$codefile])) {
            $codefileCounts[$codefile]++;
        } else {
            $codefileCounts[$codefile] = 1;
        }

        // Add the file to the response data if it occurs more than once
        if ($codefileCounts[$codefile] > 1) {
            $responseData[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'codefile' => $codefile
            ];

            // Remove the file from the database
            $entityManager->remove($file);
        }
    }

    $entityManager->flush(); // Commit the changes to the database

    return new JsonResponse($responseData);
}
*/


//DELETE files whose the same codefile with test stauts of versionnig
#[Route('/DeletefilesSamecode/{id}', name: 'DELETEFilesByDossiercode1', methods: ['DELETE'])]
public function getFilesByDossierr(int $id, DossierRepository $dossierRepository, FileRepository $fileRepository, EntityManagerInterface $entityManager): JsonResponse
{
    $dossier = $dossierRepository->find($id);

    if (!$dossier) {
        return new JsonResponse(['error' => 'Dossier not found'], Response::HTTP_NOT_FOUND);
    }

    if (!$dossier->getVersionning()) {
        return new JsonResponse(['message' => 'Le versioning de ce dossier est fermé'], Response::HTTP_BAD_REQUEST);
    }

    $files = $fileRepository->findBy(['dossier' => $id], ['date' => 'DESC']);

    if (!$files) {
        return new JsonResponse(['error' => 'No files found for dossier'], Response::HTTP_NOT_FOUND);
    }

    $codefileCounts = [];
    $responseData = [];

    foreach ($files as $file) {
        $codefile = $file->getCodefile();

        // Check if the codefile already exists in the counts array
        if (isset($codefileCounts[$codefile])) {
            $codefileCounts[$codefile]++;
        } else {
            $codefileCounts[$codefile] = 1;
        }

        // Add the file to the response data if it occurs more than once
        if ($codefileCounts[$codefile] > 1) {
            $responseData[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'codefile' => $codefile
            ];

            // Remove the file from the file system
            $filePath = $file->getPath();
                        if (file_exists($filePath)) {
                unlink($filePath);
            }
           
   
            // Remove the file from the database
            $entityManager->remove($file);
        }
    }

    $entityManager->flush(); // Commit the changes to the database

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
            'id' => $dossier->getId(),
            'versionning' => $dossier->getVersionning(),

        ];
   return new JsonResponse($data);
}


        // update file statut dans dossier , archiver le file qui est dans le dossier
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
                $file->setDate(new \DateTime());
                $entityManager->flush();
        
                return new JsonResponse(['message' => 'File status updated successfully', 'dossier' => $dossier], 200);
            } else {
                return new JsonResponse(['error' => 'You are not authorized to update this file'], 403);
            }
        }
        
        
    



        
#[Route('/archiverDossier/{id}/{email}', name: 'archiver_Dossier', methods: ['PUT'])]
public function archiverfile(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer, string $email, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['email' => $email]);

    // Find the user by email
    if (!$user) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $email)], 404);
    }

    $dossier = $entityManager->getRepository(Dossier::class)->find($id);

    if (!$dossier) {
        throw $this->createNotFoundException('Dossier not found');
    }

    // Update the status to false
    $dossier->setStatus(false);
    $entityManager->persist($dossier);
    $dossier->setDatedossier(new \DateTime());
    $entityManager->flush();

    /*
    // Send email to current user
    $email = (new Email())
        ->from('sabriskandar5@gmail.com')
        ->to($email)
        ->subject('File Archived')
        ->html('<p>Bonjour,</p>
        <p>Le fichier <strong>'. $file->getName().'</strong> a été archivé avec succès et sera automatiquement supprimé après 3 jours.</p>
        <p>Cordialement,</p>
        <p>L\'équipe de support</p>');
        
    $mailer->send($email);
    */

    return new JsonResponse(['message' => 'dossier status updated to false']);
}


#[Route('/restaurerDsossier/{id}/{email}', name: 'restaurer-dossier', methods: ['PUT'])]
public function restaurerfile(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer, string $email, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['email' => $email]);

    // Find the user by email
    if (!$user) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $email)], 404);
    }

    $dossier = $entityManager->getRepository(Dossier::class)->find($id);

    if (!$dossier) {
        throw $this->createNotFoundException('Folder not found');
    }

    $existingFolder = $entityManager->getRepository(Dossier::class)->findBy(['namedossier' => $dossier->getNamedossier(), 'user' => $user, 'status' => 1]);

    if ($existingFolder) {
        return new JsonResponse(['message' => 'Folder name already exists']);
    }

    // Update the status to false
    $dossier->setStatus(true);
    $entityManager->flush();

    return new JsonResponse(['message' => 'Dossier status updated to true']);
}



#[Route("toggle-versioning/{id}", name:"dossier_toggle_versioning", methods:["PUT"])]
     
    public function toggleVersioning(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Find the user you want to update
        $dossier = $entityManager->getRepository(Dossier::class)->findOneBy(['id' => $id]);
        if (!$dossier) {
            throw $this->createNotFoundException('dossier not found');
        }

        // Toggle the versioning status
        $dossier->setVersionning(!$dossier->isVersionning());
        $entityManager->flush();

        return new Response('Versioning status updated successfully', Response::HTTP_OK);
    }
    



    #[Route('/samecodefileBydossier/{id}', name: 'same_codebydossier', methods: ['GET'])]
public function samecodefile(Request $request, EntityManagerInterface $entityManager, int $id): JsonResponse
{
    // Find the dossier (folder) by its ID
    $dossier = $entityManager->getRepository(Dossier::class)->find($id);
    if (!$dossier) {
        throw $this->createNotFoundException('Dossier not found');
    }

    // Find files associated with the dossier
    $files = $entityManager->getRepository(File::class)->findBy(['dossier' => $dossier], ['date' => 'DESC']);

    if (!$files) {
        return new JsonResponse(['error' => 'No files found for dossier'], Response::HTTP_NOT_FOUND);
    }

    // Get the names of the files
    $fileNames = [];
    foreach ($files as $file) {
        $fileNames[] = $file->getName();
    }

    // Return the names as a JSON response
    return new JsonResponse($fileNames);
}



}
