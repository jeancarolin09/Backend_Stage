<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Invitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/events')]
class EventController extends AbstractController
{
    #[Route('', name: 'api_create_event', methods: ['POST'])]
    public function createEvent(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['title'], $data['event_date'], $data['event_time'])) {
            return new JsonResponse(['message' => 'Champs obligatoires manquants'], 400);
        }

        $event = new Event();
        $event->setTitle($data['title']);
        $event->setDescription($data['description'] ?? null);
        $event->setEventDate(new \DateTime($data['event_date']));
        $event->setEventTime(new \DateTime($data['event_time']));
        $event->setEventLocation($data['event_location'] ?? null);
        $event->setOrganizer($user);

        $em->persist($event);
        $em->flush();

        return new JsonResponse([
            'message' => 'Event created successfully!',
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'event_date' => $event->getEventDate()->format('Y-m-d'),
                'event_time' => $event->getEventTime()->format('H:i'),
                'event_location' => $event->getEventLocation(),
                'organizer' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ],
        ]);
    }

    #[Route('/{id}', name: 'get_event', methods: ['GET'])]
    public function getEvent(Event $event): JsonResponse
    {
        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'event_date' => $event->getEventDate()->format('Y-m-d'),
            'event_time' => $event->getEventTime()->format('H:i'),
            'event_location' => $event->getEventLocation(),
            'guests' => array_map(fn($inv) => [
                'name' => $inv->getName() ?: $inv->getEmail(),
                'confirmed' => $inv->isConfirmed()
            ], $event->getInvitations()->toArray()),
            'polls' => array_map(fn($poll) => [
                'id' => $poll->getId(),
                'question' => $poll->getQuestion(),
                'options' => array_map(fn($opt) => [
                    'text' => $opt->getText(),
                    'votes' => $opt->getVotes()
                ], $poll->getOptions()->toArray()),
            ], $event->getPolls()->toArray()),
        ]);
    }

    #[Route('', name: 'api_list_events', methods: ['GET'])]
    public function listEvents(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);

        $events = $em->getRepository(Event::class)->findBy(['organizer' => $user]);

        $data = array_map(fn($e) => [
            'id' => $e->getId(),
            'title' => $e->getTitle(),
            'description' => $e->getDescription(),
            'event_date' => $e->getEventDate()->format('Y-m-d'),
            'event_time' => $e->getEventTime()->format('H:i'),
            'event_location' => $e->getEventLocation(),
            'organizer' => $e->getOrganizer() ? $e->getOrganizer()->getEmail() : null,
        ], $events);

        return new JsonResponse($data);
    }
}
