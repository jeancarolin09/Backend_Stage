<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Invitation;
use App\Entity\Notification;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Uuid;
use App\Service\ActivityLogger;
use App\Service\EmailVerifier;
use App\Service\NotificationService;


#[Route('/api/invitations')]
class InvitationController extends AbstractController
{
    private EmailVerifier $emailVerifier;
    private UserRepository $userRepository;

    public function __construct(
        EmailVerifier $emailVerifier, 
        UserRepository $userRepository
    ) {
        $this->emailVerifier = $emailVerifier;
        $this->userRepository = $userRepository;
    }

    #[Route('/send', name: 'send_invitation', methods: ['POST'])]
    public function sendInvitation(Request $request, EntityManagerInterface $em, InvitationRepository $repo, ActivityLogger $activityLogger, NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
             return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $eventId = $data['event'] ?? $data['eventID'] ?? $data['eventId'] ?? null;
        
        if (!$email || !$eventId) {
            return new JsonResponse(['message' => 'Email et événement requis'], 400);
        }
        
        $event = $em->getRepository(Event::class)->find($eventId);
        if (!$event) {
            return new JsonResponse(['message' => 'Événement introuvable'], 404);
        }
        $organizer = $event->getOrganizer(); 

        //  Génération d’un token unique
        $token = Uuid::v4()->toRfc4122();

        //  Création de l’invitation
        $invitation = new Invitation();
        $invitation->setEvent($event)
            ->setEmail($email)
            ->setName($name)
            ->setToken($token)
            ->setStatus('pending')
            ->setUsed(false);
            
            // Récupérer l'utilisateur avant
            $invitedUser = $this->userRepository->findOneBy(['email' => $email]);

            // Copier la photo si elle existe
            if ($invitedUser && $invitedUser->getProfilePicture()) {
                $invitation->setProfilePicture($invitedUser->getProfilePicture());
            }

        $em->persist($invitation);
        $em->flush();

     
        // Log Activity
        // Log activité :
        $activityLogger->log(
            'send_invitation',
            $user, // L'organisateur qui envoie l'invitation
            $event,
            null,
            ['guest_name' => $invitation->getName()]
        );


         $invitedUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($invitedUser) {
            $notification = new Notification();
            $notification->setRecipient($invitedUser);
            $notification->setType('invitation_received');
            $notification->setRelatedId($invitation->getId());
            $notification->setRelatedTable('invitation');
            $notification->setIsRead(false);

            $em->persist($notification);
            $em->flush();
        }

        // 🌍 Récupération dynamique de l’URL du front
        $frontendUrl = $this->getParameter('frontend_url');
        $confirmationLink = $frontendUrl . '/confirm-invitation/' . $token;
 
        // Après avoir généré $confirmationLink
        $this->emailVerifier->sendInvitationEmail($email, $name ?? 'Invité', $event->getTitle(), $confirmationLink);

        // TODO: envoyer un email contenant $confirmationLink

        return new JsonResponse([
             'message' => 'Invitation envoyée avec succès',
                'invitation' => [
                    'id' => $invitation->getId(),
                    'name' => $invitation->getName(),
                    'email' => $invitation->getEmail(),
                    'status' => $invitation->getStatus(),
                    'used' => $invitation->isUsed(),
                    'profilePicture' => $invitedUser ? $invitedUser->getProfilePicture() : null,
                    'event' => [
                        'id' => $event->getId(),
                        'title' => $event->getTitle(),
                    ],
                    'organizer' => $organizer ? [
                        'id' => $organizer->getId(),
                        'email' => $organizer->getEmail(),
                        'name' => $organizer->getName(),
                        'profilePicture' => $organizer->getProfilePicture(),
                    ] : null,
                    'confirmation_link' => $confirmationLink,
                ],
            ]);
    }

    #[Route('/{token}/confirm', name: 'confirm_invitation', methods: ['POST'])]
        public function confirmInvitation(string $token, Request $request, EntityManagerInterface $em, InvitationRepository $repo, ActivityLogger $activityLogger): JsonResponse
        {
            $invitation = $repo->findOneBy(['token' => $token]);

            if (!$invitation) {
                return new JsonResponse(['message' => 'Invitation invalide'], 404);
            }

            if ($invitation->isUsed()) {
                return new JsonResponse(['message' => 'Invitation déjà utilisée'], 400);
            }

            $data = json_decode($request->getContent(), true);
            $status = $data['status'] ?? null; // attendu: 'accepted', 'declined', 'maybe'
            $targetUser = null;

            if (!in_array($status, ['accepted', 'declined', 'maybe'])) {
                return new JsonResponse(['message' => 'Status invalide'], 400);
            }

            $invitation->setStatus($status);
            $invitation->setUsed(true);

            // 🔹 Récupère la notification liée à cette invitation
            $notification = $em->getRepository(Notification::class)->findOneBy([
                'relatedTable' => 'invitation',
                'relatedId' => $invitation->getId(),
                'recipient' => $this->getUser() // ou l’organisateur ?
            ]);

            if ($notification) {
                $notification->setIsRead(true);
            }

            $em->flush();

            $organizer = $invitation->getEvent()->getOrganizer(); // Supposons que l'entité Event ait une méthode getOrganizer()
        
         // Log activité :
            $activityLogger->log(
                'confirm_invitation',
                $this->getUser(), // L'acteur (celui qui a fait l'action, l'utilisateur connecté)
                $invitation->getEvent(),
                $targetUser,  
                [
                    'status' => $status, // 'accepted', 'declined', 'maybe'
                    'guest_email' => $invitation->getEmail()
                ]
            );

            return new JsonResponse([
                'message' => 'Invitation confirmée',
                'eventId' => $invitation->getEvent()->getId(),
                'status' => $invitation->getStatus(),
            ]);
        }


    #[Route('/user/{email}', name: 'user_invitations', methods: ['GET'])]
    public function getUserInvitations(string $email, InvitationRepository $repo): JsonResponse
    {
        $invitations = $repo->findBy(['email' => $email]);

        $invitedUser = $this->userRepository->findOneBy(['email' => $email]);
        $data = array_map(fn($inv) => [
            'id' => $inv->getId(),
            'token' => $inv->getToken(),
            'event' => [
                'id' => $inv->getEvent()->getId(),
                'title' => $inv->getEvent()->getTitle(),
                'description' => $inv->getEvent()->getDescription(),
                'event_date' => $inv->getEvent()->getEventDate(),
                'event_time' => $inv->getEvent()->getEventTime(),
                'event_location' => $inv->getEvent()->getEventLocation(),
                'latitude' => $inv->getEvent()->getLatitude(),      // ⬅️ AJOUTÉ
                'longitude' => $inv->getEvent()->getLongitude(),
                'polls' => array_map(fn($poll) => [
                    'id' => $poll->getId(),
                    'question' => $poll->getQuestion(),
                    'options' => array_map(fn($opt) => [
                        'id' => $opt->getId(),
                        'text' => $opt->getText(),
                        'votes' => $opt->getVotes()
                    ], $poll->getOptions()->toArray()),
                ], $inv->getEvent()->getPolls()->toArray()),
            ],
            'status' => $inv->getStatus(),
            'used' => $inv->isUsed(),
            'profilePicture' => $invitedUser ? $invitedUser->getProfilePicture() : null,
            'organizer' => $inv->getEvent()->getOrganizer() ? [
            'id' => $inv->getEvent()->getOrganizer()->getId(),
            'email' => $inv->getEvent()->getOrganizer()->getEmail(),
            'name' => $inv->getEvent()->getOrganizer()->getName(),
            'profilePicture' => $inv->getEvent()->getOrganizer()->getProfilePicture(),
        ] : null,
        ], $invitations);

        return new JsonResponse($data);
    }
}
