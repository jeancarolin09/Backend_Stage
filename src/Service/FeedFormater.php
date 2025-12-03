<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\User;

class FeedFormatter
{
    public function format(Activity $activity, ?User $viewer = null): string
    {
        $actor = $activity->getActor();
        $target = $activity->getTargetUser();
        $event = $activity->getEvent();

        // Actor
        $actorName = ($actor && $viewer && $actor->getId() === $viewer->getId())
            ? 'Vous'
            : ($actor ? ($actor->getFullName() ?? 'Utilisateur inconnu') : 'Utilisateur supprimé');

        // Target
        $targetName = $target
            ? (($viewer && $target->getId() === $viewer->getId()) ? 'vous' : ($target->getFullName() ?? 'un utilisateur'))
            : 'un utilisateur supprimé';

        // Event
        $eventTitle = $event ? '« ' . $event->getTitle() . ' »' : 'un événement supprimé';

        // Action
        $action = $activity->getAction();

        // Construction du message
        if (str_contains($action, 'invité')) {
            return "$actorName a invité $targetName à l’événement $eventTitle";
        }
        if (str_contains($action, 'modifié')) {
            return "$actorName a modifié l’événement $eventTitle" . ($target ? " pour $targetName" : '');
        }
        if (str_contains($action, 'supprimé')) {
            return "$actorName a supprimé l’événement $eventTitle";
        }
        if (str_contains($action, 'créé')) {
            return "$actorName a créé l’événement $eventTitle";
        }

        return "$actorName a effectué une action sur $eventTitle";
    }
}
