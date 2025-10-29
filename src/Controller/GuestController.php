<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Invitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class GuestController extends AbstractController
{
    #[Route('/api/events/{id}/guests', name: 'add_event_guest', methods: ['POST'])]
    public function addGuest(Request $request, Event $event, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || $event->getOrganizer() !== $user) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return new JsonResponse(['message' => 'Email obligatoire'], 400);
        }

        // Vérifie si cet invité existe déjà
        $existing = $em->getRepository(Invitation::class)->findOneBy([
            'event' => $event,
            'email' => $data['email']
        ]);

        if ($existing) {
            return new JsonResponse(['message' => 'Cet invité est déjà ajouté.'], 400);
        }

        $guest = new Invitation();
        $guest->setEmail($data['email']);
        $guest->setName($data['name'] ?? null);
        $guest->setEvent($event);
        $guest->setConfirmed(false);
        $guest->setToken(bin2hex(random_bytes(16)));

        $em->persist($guest);
        $em->flush();

        return $this->json([
            'id' => $guest->getId(),
            'email' => $guest->getEmail(),
            'name' => $guest->getName(),
            'confirmed' => $guest->isConfirmed(),
            'token' => $guest->getToken(),
        ]);
    }

    #[Route('/api/events/{id}/guests/{guestId}', name: 'delete_guest', methods: ['DELETE'])]
    public function deleteGuest(Event $event, int $guestId, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || $event->getUser() !== $user) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $guest = $em->getRepository(Invitation::class)->findOneBy(['id' => $guestId, 'event' => $event]);

        if (!$guest) {
            return new JsonResponse(['message' => 'Invité non trouvé'], 404);
        }

        $em->remove($guest);
        $em->flush();

        return new JsonResponse(['message' => 'Invité supprimé avec succès']);
    }
}
