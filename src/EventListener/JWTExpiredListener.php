<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class JWTExpiredListener
{
    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $response = new JsonResponse([
            'message' => 'Votre session a expiré. Veuillez vous reconnecter.'
        ], JsonResponse::HTTP_UNAUTHORIZED);

        $event->setResponse($response);
    }
}
