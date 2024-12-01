<?php 

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        // Récupérer l'utilisateur actuellement connecté
        $user = $event->getUser();

        // Vérifiez que l'utilisateur implémente bien UserInterface
        if (!$user instanceof UserInterface) {
            return;
        }

        // Récupérez le payload actuel et ajoutez les champs personnalisés
        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['lastname'] = $user->getLastname();
        $payload['firstname'] = $user->getFirstname();
        $payload['is_verified'] = $user->getIsVerified();
        $payload['exp'] = strtotime("tomorrow 0:00"); // Le lendemain à minuit

        // Redéfinir le payload dans l'événement
        $event->setData($payload);
    }
}
