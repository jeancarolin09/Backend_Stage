<?php
// src/EventListener/AuthenticationSuccessListener.php
namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;

class AuthenticationSuccessListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user) {
            return;
        }
        
        
        // Mettre à jour le statut en ligne
        $user->setIsOnline(true);
        $user->setLastActivity(new \DateTime());
        $this->em->persist($user);
        $this->em->flush();
        // Ajoute les infos utilisateur à la réponse JSON
        $data['user'] = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'profilePicture' => $user->getProfilePicture(),
            'isOnline' => $user->getIsOnline(),
        ];

        $event->setData($data);
    }
}
