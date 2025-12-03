<?php

namespace App\Controller;


use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'get_notifications', methods: ['GET'])]
    public function getNotifications(NotificationRepository $repo): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $unread = $repo->count([
            'recipient' => $user,
            'isRead' => false
        ]);

        $notifications = $repo->findBy(
            ['recipient' => $user],
            ['createdAt' => 'DESC']
        );

        // 🔥 Transforme les entités en tableau
          $data = array_map(fn($notif) => [
            'id' => $notif->getId(),
            'isRead' => $notif->isIsRead(),
            'relatedTable' => $notif->getRelatedTable(),
            'relatedId' => $notif->getRelatedId(),
        ], $notifications);

        return new JsonResponse([
            'count' => $unread,
            'notifications' => $data
        ]);
    }

    #[Route('/messages/mark-as-read', name: 'mark_as_read', methods: ['POST'])]
    public function markAsRead(NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $notifications = $repo->findBy([
            'recipient' => $user,
            'isRead' => false
        ]);

        foreach ($notifications as $notif) {
            $notif->setIsRead(true);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

//     #[Route('/messages/{id}/mark-as-read', name: 'notification_message_mark_read', methods: ['POST'])]
// public function markMessageNotificationAsRead(int $id, NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
// {
//     $user = $this->getUser();
//     $notification = $repo->find($id);

//     if (!$notification || $notification->getRecipient() !== $user || $notification->getRelatedTable() !== 'message') {
//         return new JsonResponse(['error' => 'Unauthorized or not found'], 404);
//     }

//     $notification->setIsRead(true);
//     $em->flush();

//     // Retourne le nombre de notifications non lues restantes
//     $unreadCount = $repo->count(['recipient' => $user, 'isRead' => false, 'relatedTable' => 'message']);

//     return new JsonResponse([
//         'success' => true,
//         'unreadCount' => $unreadCount
//     ]);
// }


    
}
