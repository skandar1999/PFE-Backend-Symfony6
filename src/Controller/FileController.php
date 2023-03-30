<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use Symfony\Component\Mime\Email;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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

    // Create a new File entity and set its properties
    $file = new File();
    $file->setName($name);
    $file->setDate(new \DateTime());
    $file->setUser($user);

    // Move the uploaded file to a directory on the server
    $uploadDir = $this->getParameter('uploads_directory');
    $originalName = $uploadedFile->getClientOriginalName();
    $uploadedFile->move($uploadDir, $originalName);
    $file->setPath($uploadDir.'/'.$originalName);

    // Persist the File entity to the database
    $entityManager->persist($file);
    $entityManager->flush();

    return new Response('File uploaded successfully.');
}



#[Route('/getfiles/{email}', name: 'get_user_files', methods: ['GET'])]
public function getUserFiles(string $email, EntityManagerInterface $entityManager): JsonResponse
{
$user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

if (!$user) {
    throw $this->createNotFoundException('User not found');
}

$files = $entityManager->getRepository(File::class)->findBy(['user' => $user], ['date' => 'DESC']);

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
 


#[Route('/archiver/{id}/{email}', name: 'delete_files', methods: ['PUT'])]
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
    $entityManager->flush();

    // Send email to current user
    $email = (new Email())
        ->from('sabriskandar5@gmail.com')
        ->to($email)
        ->subject('File Archived')
        ->html('<p>Bonjour,</p>
        <p>Le fichier <strong>'. $file->getName().'</strong> a été archivé avec succès.</p>
        <p>Cordialement,</p>
        <p>L\'équipe de support</p>');
        
    $mailer->send($email);

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
}