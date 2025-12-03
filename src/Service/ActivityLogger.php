<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Activity;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\Notification;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }
     
    /**
     * @param string $action Code d'action (ex: 'create_event', 'join')
     * @param User|null $actor Utilisateur effectuant l'action
     * @param Event|null $event Événement concerné
     * @param User|null $targetUser Utilisateur cible (ex: invité)
     */

    public function log(string $action, ?User $actor = null, ?Event $event = null, ?User $targetUser = null, array $details = []): void
    {
        $activity = new Activity();

        // ✅ Enregistrement de l’activité
        $activity->setActor($actor);
        $activity->setTargetUser($targetUser);
        $activity->setEvent($event);
        $activity->setAction($action);
        if (!empty($details)) {
             $activity->setDetails($details); 
        }
        
        $this->em->persist($activity);
        $this->em->flush();
    
       
        $recipient = $targetUser ?? $actor;

        if ($recipient) {
            $notification = new Notification();
            $notification->setRecipient($recipient);
            $notification->setType('activity');
            $notification->setRelatedTable('activity');
            $notification->setRelatedId($activity->getId());
            $notification->setIsRead(false); // badge = non lu

            $this->em->persist($notification);
            $this->em->flush();
        }
    }
}
