<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Doctrine\ORM\EntityManagerInterface;

class UserActivityListener
{
    
     private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        
        $this->em = $em;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) return;

        $request = $event->getRequest();

        // Récupérer l'utilisateur à partir de l'attribut fourni par LexikJWT
        /** @var UserInterface|null $user */
        $user = $request->attributes->get('_security_user');

        if (!$user) return;

        $user->setLastActivity(new \DateTime());
        $user->setIsOnline(true);

        $this->em->persist($user);
        $this->em->flush();
    }
}
