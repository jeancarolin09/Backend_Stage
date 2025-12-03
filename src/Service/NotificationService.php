<?php

namespace App\Service;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function createNotification(?User $recipient, string $type, ?int $relatedId, ?string $relatedTable): Notification
    {
        $notif = new Notification();
        $notif->setRecipient($recipient)
              ->setType($type)
              ->setRelatedId($relatedId)
              ->setRelatedTable($relatedTable)
              ->setIsRead(false);

        $this->em->persist($notif);
        $this->em->flush();

        return $notif;
    }
}
