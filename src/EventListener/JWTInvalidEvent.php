<?php 

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class JWTListener
{
    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $response = new JsonResponse([
            'JsonResponse' => 'Token invalide. Veuillez vous reconnecter.'
        ], JsonResponse::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $response = new JsonResponse([
            'message' => 'Aucun token trouvé. Vous devez être connecté pour accéder à cette ressource.'
        ], JsonResponse::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }

    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $response = new JsonResponse([
            'JsonResponse' => 'Votre session a expiré. Veuillez vous reconnecter.'
        ], JsonResponse::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }
}
