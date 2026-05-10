<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        $user = $event->getUser();

        $payload = $event->getData();

        // ➕ Ajouter l'id au JWT
        $payload['id'] = $user->getId();
        $payload['name'] = $user->getName();
        $payload['email'] = $user->getEmail();

        $event->setData($payload);
    }
}
