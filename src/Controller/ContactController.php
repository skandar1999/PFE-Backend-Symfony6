<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ContactController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/contactuser', name: 'contact_user', methods:'POST')]
    public function contact(Request $request): JsonResponse
    {
        // Get the data from the request body
        $data = json_decode($request->getContent(), true);
    
        // Check if the emailUser key is set
        if (!isset($data['emailUser'])) {
            return new JsonResponse(['error' => 'Missing emailUser'], 400);
        }
    
        // Create a new Contact object and set its properties
        $contact = new Contact();
        $contact->setEmailUser($data['emailUser']);
        $contact->setDate(new \DateTime());
    
        // Check if the description key is set
        if (isset($data['description'])) {
            $contact->setDescription($data['description']);
        }
    
        // Persist the contact object to the database
        $this->entityManager->persist($contact);
        $this->entityManager->flush();
    
        // Return a success message
        return new JsonResponse(['message' => 'Nous avons bien reçu votre message !']);
    }
    

#[Route('/getallmessages', name: 'get_all_messages', methods:'GET')]
public function getAllMessages(ContactRepository $contactRepository): JsonResponse
{
    $startDate = new \DateTime('-15 days'); // Get the date three days ago
    $endDate = new \DateTime(); // Get the current date

    $contacts = $contactRepository->createQueryBuilder('c')
        ->andWhere('c.date BETWEEN :startDate AND :endDate')
        ->setParameter('startDate', $startDate)
        ->setParameter('endDate', $endDate)
        ->orderBy('c.date', 'DESC')
        ->getQuery()
        ->getResult();

    $response = [];
    foreach ($contacts as $contact) {
        $response[] = [
            'id' => $contact->getId(),
            'emailUser' => $contact->getEmailUser(),
            'description' => $contact->getDescription(),
            'date' => $contact->getDate() ? $contact->getDate()->format('d-m-y H:i') : null,
            'status' => $contact->getStatus(),
        ];
    }

    return new JsonResponse($response, 200);
}





#[Route('/deleteMessage/{id}', name: 'delete-message', methods:'DELETE')]
public function delete( int $id , EntityManagerInterface $entityManager): Response
{
    $message  = $entityManager->getRepository(Contact::class)->find($id);

    if (!$message) {
        return $this->json('No message found for id' . $id, 404);
    }

    $entityManager->remove($message);
    $entityManager->flush();

    return $this->json('Deleted a messages successfully with id ' . $id);
}




#[Route('/updateStatut/{id}', name: 'updateStatut-status', methods:'put')]
public function update(int $id , EntityManagerInterface $entityManager, Contact $contact): Response
{   


    $message  = $entityManager->getRepository(Contact::class)->find($id);

    if (!$message) {
        return $this->json('No message found for id' . $id, 404);
    }
    $contact->setStatus(true);

    $entityManager->flush();

    return $this->json($contact);
}




}

        
    

