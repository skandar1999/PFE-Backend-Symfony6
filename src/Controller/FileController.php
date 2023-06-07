<?php

namespace App\Controller;

use finfo;
use App\Entity\File;
use App\Entity\User;
use Box\Spout\Common\Type;
use Symfony\Component\Mime\Email;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Writer\Common\Creator\WriterFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FileController extends AbstractController
{
    
    #[Route('/Fileuploade/{email}', name: 'upload_file', methods: ['POST'])]
    public function uploadFile(string $email, Request $request, EntityManagerInterface $entityManager): Response
{
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    $uploadedFile = $request->files->get('files');
    $name = $request->request->get('name') ?? $uploadedFile->getClientOriginalName();
    $size = $uploadedFile->getSize();

    // Check if a file with the given name already exists in the database for this user
    $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'user' => $user, 'status' => true], ['version' => 'DESC']);
    if ($existingFiles) {
        $version = $existingFiles[0]->getVersion() + 1;
        $codefile = $existingFiles[0]->getCodefile();
    } else {
        $version = 1;
        $randomBytes = random_int(1100, 9999);
        $codefile = bin2hex($randomBytes);
    }

    // Append the version number to the file name
    $originalName = $uploadedFile->getClientOriginalName();
    $parts = pathinfo($originalName);
    $extension = isset($parts['extension']) ? '.' . $parts['extension'] : '';
    $basename = basename($originalName, $extension);

    // Check if a file with the given name already exists in the database for this user
    $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $originalName, 'user' => $user, 'status' => true]);
    if ($existingFiles) {
        $i = 2;
        do {
            $name = $basename . ' (' . $i . ')'  . $extension;
            $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'user' => $user, 'status' => true]);
            $i++;
        } while ($existingFiles);
        $version = $i - 1;
    } else {
        $name = $originalName;
    }

    $file = new File();
    $file->setName($name);
    $file->setDate(new \DateTime());
    $file->setUser($user);
    $file->setVersion($version);
    $file->setCodefile(strval($codefile));
    $file->setSize($size);
    $sizeHumanReadable = $file->getSizeHumanReadable();
    $file->setSize($sizeHumanReadable);

    // Move the uploaded file to a directory on the server
    $uploadDir = $this->getParameter('uploads_directory');
    $uploadedFile->move($uploadDir, $name);
    $file->setPath($uploadDir.'/'.$name);

    // Persist the File entity to the database
    $entityManager->persist($file);
    $entityManager->flush();

    return new Response('File uploaded successfully.');
}




#[Route('/checkfileUser/{email}', name: 'checkfileforuser', methods: ['POST'])]
    public function checkFileExists(string $email, Request $request, EntityManagerInterface $entityManager)
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }
    
        $uploadedFile = $request->files->get('file');
        $name = $request->request->get('name') ?? $uploadedFile->getClientOriginalName();
    
        $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'user' => $user], ['version' => 'DESC']);
        $fileExists = !empty($existingFiles);
    
        return new JsonResponse(['exists' => $fileExists]);
    }



// FileuploadAndReplace as dossierID
#[Route('/FileuploadAndReplaceDocs/{email}', name: 'Fileuploadever_file', methods: ['POST'])]
public function uploadFilever(string $email, Request $request, EntityManagerInterface $entityManager, Filesystem $filesystem): Response
{
    // Load the dossier by ID
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    // Get the uploaded file and its properties
    $uploadedFile = $request->files->get('files');
    $name = $request->request->get('name') ?? $uploadedFile->getClientOriginalName();
    $size = $uploadedFile->getSize();

    // Check if a file with the given name already exists in the database for this dossier
    $existingFiles = $entityManager->getRepository(File::class)->findBy(['name' => $name, 'dossier' => $user]);
    foreach ($existingFiles as $existingFile) {
        
        // Delete the existing file
        $path = $existingFile->getPath();
        $filesystem->remove($path);
        $entityManager->remove($existingFile);
    }
    
    $randomBytes = random_bytes(5);
    $codefile = bin2hex($randomBytes);

    // Create a new File entity and set its properties
    $file = new File();
    $file->setName($name);
    $file->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
    $file->setVersion(1);
    $file->setSize($size);
    $sizeHumanReadable = $file->getSizeHumanReadable();
    $file->setSize($sizeHumanReadable);
    $file->setCodefile(intval(bin2hex($randomBytes)));

    // Move the uploaded file to a directory on the server
    $uploadDir = $this->getParameter('uploads_directory');
    $uploadedFile->move($uploadDir, $name);
    $file->setPath($uploadDir.'/'.$name);

    // Persist the File entity to the database
    $entityManager->persist($file);
    $entityManager->flush();

    return new Response('File uploaded successfully.');
}   
    

#[Route('/getfiles/{email}', name: 'get_user_files', methods: ['GET'])]
public function getUserFiles(string $email, Request $request, EntityManagerInterface $entityManager): JsonResponse
{
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    $name = $request->query->get('name');

    if ($name) {
        $files = $entityManager->getRepository(File::class)->findBy(['user' => $user, 'name' => $name], ['date' => 'DESC']);
    } else {
        $files = $entityManager->getRepository(File::class)->findBy(['user' => $user], ['date' => 'DESC']);
    }

    $responseData = [];

    foreach ($files as $file) {
        $responseData[] = [
            'id' => $file->getId(),
            'user_id' => $file->getUser()->getId(),
            'name' => $file->getName(),
            'date' => $file->getDate() ? $file->getDate()->format('d-m-y H:i') : null,
            'path' => $file->getPath(),
            'status'=> $file->getStatus(),
            'size'=> $file->getSize(),

        ];
    }

    return new JsonResponse($responseData);
}

 

#[Route('/archiver/{id}/{email}', name: 'archiver_files', methods: ['PUT'])]
public function archiverfile(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer, string $email, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['email' => $email]);

    // Find the user by email
    if (!$user) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $email)], 404);
    }

    $file = $entityManager->getRepository(File::class)->find($id);

    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

   

    // Update the status to false
    $file->setStatus(false);
    $entityManager->persist($file);
    $file->setDate(new \DateTime());
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

    return new JsonResponse(['message' => 'File status updated to false']);
}




#[Route('/findbyName/{name}', name: 'findbyname', methods: ['GET'])]
public function findUser2(string $name, File $fileRepository , EntityManagerInterface $entityManager): JsonResponse
{
    $file = $entityManager->getRepository(File::class)->findOneBy(['name' => $name]);

    if (!$file) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $name )], 404);
    }
    return $this->json([
        'id' => $file->getId(),
        'fileName' => $file->getName(),    
    ]);
}



#[Route('/deletefilefromarchive/{id}', name: 'delete_files_fromarchive', methods: ['DELETE'])]
public function deletefilefromarchive(int $id, EntityManagerInterface $entityManager): JsonResponse
{
    $file = $entityManager->getRepository(File::class)->find($id);

    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    // Remove the file from the server
    $filePath = $file->getPath();
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Remove the file from the database
    $entityManager->remove($file);
    $entityManager->flush();

    return new JsonResponse(['message' => 'File deleted successfully']);
}



#[Route('/rename_file/{id}', name: 'rename_file', methods: ['POST'])]
public function renameFile(Request $request, EntityManagerInterface $entityManager, int $id): Response
{
    $file = $entityManager->getRepository(File::class)->find($id);
    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    $name = $request->request->get('name');
    if (!$name) {
        throw $this->createNotFoundException('New name not specified');
    }

    // Get the directory and the original filename
    $path = $file->getPath();
    $originalName = basename($path);

    // Get the extension of the file
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);

    // Generate the new filename with the new name and the same extension
    $newName = $name . '.' . $extension;
    $newPath = str_replace($originalName, $newName, $path);

    // Rename the file by replacing the original name with the new name
    if (!rename($path, $newPath)) {
        throw new \Exception('Error renaming file');
    }

    // Update the File entity with the new path and name
    $file->setName($newName);
    $file->setPath($newPath);
    $entityManager->flush();

    return new Response('File renamed successfully.');
}




#[Route('/restaurerfile/{id}/{email}', name: 'restaurer-file', methods: ['PUT'])]
public function restaurerfile(int $id, EntityManagerInterface $entityManager, MailerInterface $mailer, string $email, UserRepository $userRepository): JsonResponse
{
    $user = $userRepository->findOneBy(['email' => $email]);

    // Find the user by email
    if (!$user) {
        return $this->json(['error' => sprintf('User with username "%s" not found.', $email)], 404);
    }

    $file = $entityManager->getRepository(File::class)->find($id);

    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    // Update the status to false
    $file->setStatus(true);
    $entityManager->persist($file);
    $entityManager->flush();

   

    return new JsonResponse(['message' => 'File status updated to true']);
}



#[Route('/getfilesArchive/{email}', name: 'get_user_filesArchive', methods: ['GET'])]
public function getUserFilesArchive(string $email, EntityManagerInterface $entityManager): JsonResponse
{
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    $yesterday = new \DateTime();
    $yesterday->modify('-3 day');
    
    $queryBuilder = $entityManager->createQueryBuilder();
    $queryBuilder->select('f')
        ->from(File::class, 'f')
        ->where('f.user = :user')
        ->andWhere('f.date >= :yesterday')
        ->orderBy('f.date', 'DESC')
        ->setParameter('user', $user)
        ->setParameter('yesterday', $yesterday);
    
    $files = $queryBuilder->getQuery()->getResult();

    $responseData = [];

    foreach ($files as $file) {
        $responseData[] = [
            'id' => $file->getId(),
            'user_id' => $file->getUser()->getId(),
            'name' => $file->getName(),
            'date' => $file->getDate()->format('Y-m-d'),
            'path' => $file->getPath(),
            'status'=> $file->getStatus(),
        ];
    }

    return new JsonResponse($responseData);
}


#[Route('/Filedownload/{name}', name: 'download_file', methods: ['GET'])]
public function downloadFile(string $name, EntityManagerInterface $entityManager): Response
{
    $file = $entityManager->getRepository(File::class)->find($name);
    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    // Generate response
    $response = new Response();
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getName());
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Type', mime_content_type($file->getPath()));
    $response->setContent(file_get_contents($file->getPath()));

    return $response;
}





#[Route('/getpathofile/{id}', name: 'getpathofileE', methods: ['GET'])]
public function getpathofile(Request $request, EntityManagerInterface $entityManager, int $id): Response
{
    $file = $entityManager->getRepository(File::class)->find($id);
    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    // Get the directory and the original filename
   
    
    // Return the file path as a JSON response
    return $this->json([
        'id' => $file->getPath(),
        'fileName' => $file->getName(),    
    ]);
}

/*

#[Route('/update-file/{id}', name: 'updatefileee', methods: ['POST'])]
public function updateFileAction(Request $request, EntityManagerInterface $entityManager, int $id): Response
{
    // Create the absolute file path
    $file = $entityManager->getRepository(File::class)->find($id);
    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    // Verify if the file exists
    if (!file_exists($file->getPath())) {
        return new Response(json_encode(['error' => 'Le fichier n\'existe pas']), Response::HTTP_NOT_FOUND);
    }

    // Get the new content from the request
    $jsonData = $request->getContent();
    $data = json_decode($jsonData, true);
    $newContent = $data['content'];
    
    // Modify the file content with Spout
    $reader = ReaderEntityFactory::createXLSXReader();
    $reader->open($file);
    $writer = WriterEntityFactory::createXLSXWriter();
    $writer->openToFile($file);

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            // Modify the content of the row with the new data
            $rowData = $row->toArray();
            $rowData[0] = $newContent;
            $newRow = WriterEntityFactory::createRowFromArray($rowData);
            $writer->addRow($newRow);
        }
    }

    $reader->close();
    $writer->close();

    return new Response(json_encode(['success' => true]), Response::HTTP_OK);
}
*/

#[Route('/samecodefile/{id}', name: 'same_code', methods: ['GET'])]
public function samecodefile(Request $request, EntityManagerInterface $entityManager, int $id): JsonResponse
{
    $file = $entityManager->getRepository(File::class)->find($id);
    if (!$file) {
        throw $this->createNotFoundException('File not found');
    }

    // Get the code of the file
    $code = $file->getCodefile();

    // Find files with the same code
    $filesWithSameCode = $entityManager->getRepository(File::class)->findBy(['codefile' => $code]);

    // Get the names of the files
    $fileNames = [];
    foreach ($filesWithSameCode as $file) {
        $fileNames[] = $file->getName();
    }

    // Return the names as a JSON response
    return new JsonResponse($fileNames);
}



}