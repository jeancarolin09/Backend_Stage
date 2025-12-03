<?php

namespace App\Controller;

use App\Repository\ActivityRepository;
use App\Repository\NotificationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Activity;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/activities')]
class ActivityController extends AbstractController
{
    private ActivityRepository $activityRepository;
    private LoggerInterface $logger;
    private NotificationRepository $notificationRepository;

    public function __construct(ActivityRepository $activityRepository, LoggerInterface $logger, NotificationRepository $notificationRepository)
    {
        $this->activityRepository = $activityRepository;
        $this->logger = $logger;
        $this->notificationRepository = $notificationRepository;
    }

    private function formatUser(?User $user, User $viewer): array
    {
        if (!$user) return ['id' => null, 'name' => 'Utilisateur supprimé'];
        $name = $user->getId() === $viewer->getId() ? 'Vous' : ($user->getName() ?? $user->getUserIdentifier());
        return ['id' => $user->getId(), 'name' => $name];
    }

    private function formatActivity(Activity $activity, User $viewer): array
    {
        $event = $activity->getEvent();

          // 🔥 Récupère la notification correspondant à cette activité
    $notif = $this->notificationRepository->findOneBy([
        'recipient' => $viewer,
        'relatedTable' => 'activity',
        'relatedId' => $activity->getId()
    ]);
        return [
            'id' => $activity->getId(),
            'actor' => $this->formatUser($activity->getActor(), $viewer),
            'targetUser' => $this->formatUser($activity->getTargetUser(), $viewer),
            'action' => $this->generateActivityMessage($activity, $viewer),
            'event' => $event ? [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'organizer' => $this->formatUser($event->getOrganizer(), $viewer)
            ] : null,
            'createdAt' => $activity->getCreatedAt()?->format(\DateTime::ATOM),
            'isRead' => $notif ? $notif->isIsread() : false,
        ];
    }

    #[Route('', name: 'api_get_activities', methods: ['GET'])]
    public function getActivities(): JsonResponse
    {
        try {
            $viewer = $this->getUser();
            if (!$viewer) return $this->json(['error' => 'Unauthorized'], 401);

            $activities = $this->activityRepository->findByUserOrRelatedSafe($viewer);
            $unreadCount = $this->notificationRepository->count([
                    'recipient' => $viewer,
                    'isRead' => false,
                ]);

            $data = array_map(fn($a) => $this->formatActivity($a, $viewer), $activities);

            return $this->json(['activities' => $data, 'unreadCount' => $unreadCount]);

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur récupération activités : ' . $e->getMessage());
            return $this->json([
                'message' => 'Erreur serveur interne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

//     #[Route('/{id}/read', methods: ['POST'])]
//     public function markAsRead(Activity $activity): JsonResponse
//     {
//         $user = $this->getUser();
//         if (!$user) return $this->json(['error' => 'Unauthorized'], 401);

//        if (!$activity->getTargetUser() || !$activity->getActor() || 
//     ($activity->getTargetUser()->getId() !== $user->getId() && $activity->getActor()->getId() !== $user->getId())) {
//     return new JsonResponse(['error' => 'Not allowed'], 403);
// }

//        try {
//     $activity->setIsRead(true);
//     $this->activityRepository->save($activity, true);
//     return $this->json(['success' => true]);
// } catch (\Exception $e) {
//     $this->logger->error('❌ Erreur mark-as-read : ' . $e->getMessage());
//     return $this->json([
//         'message' => 'Erreur serveur interne',
//         'error' => $e->getMessage()
//     ], 500);
// }

//     }

//     #[Route('/mark-all-read', methods: ['POST'])]
//     public function markAllAsRead(): JsonResponse
//     {
//         $user = $this->getUser();
//         if (!$user) return $this->json(['error' => 'Unauthorized'], 401);

//         try {
//             $activities = $this->activityRepository->findByUserOrRelatedSafe($user);
//             foreach ($activities as $activity) {
//                 if (!$activity->isRead()) {
//                     $activity->setIsRead(true);
//                     $this->activityRepository->save($activity, false);
//                 }
//             }
//             $this->activityRepository->flush();
//             return $this->json(['success' => true]);
//         } catch (\Exception $e) {
//             $this->logger->error('❌ Erreur mark-all-read : ' . $e->getMessage());
//             return $this->json([
//                 'message' => 'Erreur serveur interne',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

 #[Route('/mark-all-read', name: 'notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $repo = $em->getRepository(Notification::class);

        $notifications = $repo->findBy([
            'recipient' => $user,
            'isRead' => false
        ]);

        foreach ($notifications as $notif) {
            $notif->setIsRead(true);
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Notifications marked as read'
        ]);
    }
    private function generateActivityMessage(Activity $activity, User $viewer): string
    {
        $actor = $activity->getActor();
        $targetUser = $activity->getTargetUser();
        $event = $activity->getEvent();
        $actionCode = $activity->getAction(); // C'est le code brut (ex: 'create_event')

        $actorName = $actor ? $this->formatUser($actor, $viewer)['name'] : "Quelqu’un";
        $targetName = $targetUser ? $this->formatUser($targetUser, $viewer)['name'] : null;
        $eventName = $event ? $event->getTitle() : "un événement supprimé";
        
        $isViewerActor = $actor && $actor->getId() === $viewer->getId();
        $isViewerTarget = $targetUser && $targetUser->getId() === $viewer->getId();
        $pollQuestion = $activity->getDetails()['poll_question'] ?? 'un sondage'; // Extrait les détails
        $guestName = $activity->getDetails()['guest_name'] ?? 'un invité';
        $guestEmail = $activity->getDetails()['guest_email'] ?? 'un invité';
        $invitationStatus = $activity->getDetails()['status'] ?? 'une réponse';

        switch ($actionCode) {
            case 'create_event':
                return $isViewerActor 
                    ? "Vous avez créé un nouvel événement '$eventName' 🎉"
                    : "$actorName a créé un événement '$eventName' 🎉";

            case 'update_event':
                return $isViewerActor 
                    ? "Vous avez modifié l'événement '$eventName' ✏️"
                    : "$actorName a modifié l'événement '$eventName' ✏️";

            case 'delete_event':
                return $isViewerActor 
                    ? "Vous avez supprimé l'événement '$eventName' 🗑️"
                    : "$actorName a supprimé l'événement '$eventName' 🗑️";

            case 'join':
                return $isViewerActor 
                    ? "Vous avez rejoint l'événement '$eventName' ➕"
                    : "$actorName a rejoint l'événement '$eventName' ➕";
            
            case 'confirm_presence':
                // Ce cas est pour l'utilisateur connecté qui change son statut (déjà couvert par join/decline/maybe via la réponse à l'invitation)
                return $isViewerActor 
                    ? "Vous avez confirmé votre présence à l'événement '$eventName' 👍"
                    : "$actorName a confirmé sa présence à l'événement '$eventName' 👍";

            // --- NOUVEAUX CAS D'ACTION SONDAGE ---
            case 'create_poll':
                return $isViewerActor
                    ? "Vous avez créé un nouveau sondage '$pollQuestion' pour l'événement '$eventName' 📊"
                    : "$actorName a créé un nouveau sondage '$pollQuestion' pour l'événement '$eventName' 📊";

            case 'update_poll':
                return $isViewerActor
                    ? "Vous avez modifié le sondage '$pollQuestion' de l'événement '$eventName' 🔄"
                    : "$actorName a modifié le sondage '$pollQuestion' de l'événement '$eventName' 🔄";

            case 'delete_poll':
                return $isViewerActor
                    ? "Vous avez supprimé le sondage '$pollQuestion' de l'événement '$eventName' ❌"
                    : "$actorName a supprimé le sondage '$pollQuestion' de l'événement '$eventName' ❌";

            case 'vote':
                // L'information sur l'acteur et l'événement est suffisante ici
                return $isViewerActor 
                    ? "Vous avez voté sur le sondage '$pollQuestion' de l'événement '$eventName' ✔️"
                    : "$actorName a voté sur le sondage '$pollQuestion' de l'événement '$eventName' ✔️";

            case 'unvote':
                // L'information sur l'acteur et l'événement est suffisante ici
                return $isViewerActor 
                    ? "Vous avez annulé votre vote sur le sondage '$pollQuestion' de l'événement '$eventName' "
                    : "$actorName a annulé son vote sur le sondage '$pollQuestion' de l'événement '$eventName' ";
            
            // --- NOUVEAUX CAS D'ACTION INVITATION/INVITÉS ---
            case 'add_guest':
                return $isViewerActor
                    ? "Vous avez invité $guestName à l'événement '$eventName' 📧"
                    : "$actorName  Vous a invité à l'événement '$eventName' 📧";

            case 'delete_guest':
                return $isViewerActor
                    ? "Vous avez supprimé l'invité $guestName de l'événement '$eventName' 🗑️"
                    : "$actorName a supprimé l'invité $guestName de l'événement '$eventName' 🗑️";
                    
            case 'send_invitation':
                return $isViewerActor
                    ? "Vous avez envoyé une invitation à $guestName pour l'événement '$eventName' ✉️"
                    : "$actorName a envoyé une invitation à $guestName pour l'événement '$eventName' ✉️";
                    
             case 'receive_invitation':
                return $isViewerActor
                    ? "Vous avez reçu une invitation de $targetName pour l'événement '$eventName' ✉️"
                    : "$actorName a reçu une invitation de $targetName pour l'événement '$eventName' ✉️";

            case 'confirm_invitation':
    $statusEmoji = match (strtolower($invitationStatus)) {
        'accepted' => '✅',
        'declined' => '🚫',
        'maybe' => '🤔',
        default => '📝',
    };

    // Nom affiché selon la situation
    $guestDisplay = $guestName !== 'un invité' ? $guestName : $guestEmail;
    $targetNameForMessage = $targetUser ? $targetName : $guestDisplay;

    // 💬 Si c’est l’utilisateur connecté (celui qui a confirmé)
    if ($isViewerTarget) {
        return match (strtolower($invitationStatus)) {
            'accepted' => "Vous avez accepté votre invitation à l'événement '$eventName' $statusEmoji",
            'declined' => "Vous avez décliné votre invitation à l'événement '$eventName' $statusEmoji",
            'maybe' => "Vous avez indiqué que vous viendrez peut-être à l'événement '$eventName' $statusEmoji",
            default => "Vous avez répondu '$invitationStatus' à l'invitation pour l'événement '$eventName' $statusEmoji",
        };
    }

    // 💬 Si c’est l’organisateur ou un autre utilisateur qui voit
    return match (strtolower($invitationStatus)) {
        'accepted' => "$targetNameForMessage a accepté son invitation à l'événement '$eventName' $statusEmoji",
        'declined' => "$targetNameForMessage a décliné son invitation à l'événement '$eventName' $statusEmoji",
        'maybe' => "$targetNameForMessage a indiqué qu’il viendra peut-être à l'événement '$eventName' $statusEmoji",
        default => "$targetNameForMessage a répondu '$invitationStatus' à l'invitation pour l'événement '$eventName' $statusEmoji",
    };

            default:
                return $isViewerActor 
                    ? "Vous avez effectué une action inconnue ($actionCode)" 
                    : "$actorName a effectué une action inconnue ($actionCode)";
        }
    }
}
