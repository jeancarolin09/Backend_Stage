<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Invitation;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Uuid;

#[Route('/api/invitations')]
class InvitationController extends AbstractController
{
    #[Route('/send', name: 'send_invitation', methods: ['POST'])]
    public function sendInvitation(Request $request, EntityManagerInterface $em, InvitationRepository $repo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $eventId = $data['eventId'] ?? null;

        if (!$email || !$eventId) {
            return new JsonResponse(['message' => 'Email et événement requis'], 400);
        }

        $event = $em->getRepository(Event::class)->find($eventId);
        if (!$event) {
            return new JsonResponse(['message' => 'Événement introuvable'], 404);
        }

        // 🔑 Génération d’un token unique
        $token = Uuid::v4()->toRfc4122();

        // 💾 Création de l’invitation
        $invitation = new Invitation();
        $invitation->setEvent($event)
            ->setEmail($email)
            ->setName($name)
            ->setToken($token)
            ->setConfirmed(false)
            ->setUsed(false);

        $em->persist($invitation);
        $em->flush();

        // 🌍 Récupération dynamique de l’URL du front
        $frontendUrl = $this->getParameter('frontend_url');
        $confirmationLink = $frontendUrl . '/confirm-invitation/' . $token;

        // TODO: envoyer un email contenant $confirmationLink

        return new JsonResponse([
            'message' => 'Invitation envoyée avec succès',
            'invitationId' => $invitation->getId(),
            'confirmation_link' => $confirmationLink,
        ]);
    }

    #[Route('/{token}/confirm', name: 'confirm_invitation', methods: ['POST'])]
    public function confirmInvitation(string $token, EntityManagerInterface $em, InvitationRepository $repo): JsonResponse
    {
        $invitation = $repo->findOneBy(['token' => $token]);

        if (!$invitation) {
            return new JsonResponse(['message' => 'Invitation invalide'], 404);
        }

        if ($invitation->isUsed()) {
            return new JsonResponse(['message' => 'Invitation déjà utilisée'], 400);
        }

        $invitation->setConfirmed(true);
        $invitation->setUsed(true);
        $em->flush();

        return new JsonResponse([
            'message' => 'Invitation confirmée',
            'eventId' => $invitation->getEvent()->getId()
        ]);
    }

    #[Route('/user/{email}', name: 'user_invitations', methods: ['GET'])]
    public function getUserInvitations(string $email, InvitationRepository $repo): JsonResponse
    {
        $invitations = $repo->findBy(['email' => $email]);

        $data = array_map(fn($inv) => [
            'id' => $inv->getId(),
            'token' => $inv->getToken(),
            'event' => [
                'id' => $inv->getEvent()->getId(),
                'title' => $inv->getEvent()->getTitle(),
                'event_date' => $inv->getEvent()->getEventDate(),
                'event_time' => $inv->getEvent()->getEventTime(),
                'event_location' => $inv->getEvent()->getEventLocation(),
                'polls' => array_map(fn($poll) => [
                    'id' => $poll->getId(),
                    'question' => $poll->getQuestion(),
                    'options' => array_map(fn($opt) => [
                        'text' => $opt->getText(),
                        'votes' => $opt->getVotes()
                    ], $poll->getOptions()->toArray()),
                ], $inv->getEvent()->getPolls()->toArray()),
            ],
            'confirmed' => $inv->isConfirmed(),
            'used' => $inv->isUsed(),
        ], $invitations);

        return new JsonResponse($data);
    }
}
