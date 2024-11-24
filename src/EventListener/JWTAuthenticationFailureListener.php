<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class JWTAuthenticationFailureListener
{
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        // Message personnalisé
        $data = [
            'message' => 'Échec de l’authentification. Veuillez vérifier vos identifiants.',
        ];

        // Définir une réponse JSON avec un statut 401
        $response = new JsonResponse($data, JsonResponse::HTTP_UNAUTHORIZED);

        // Appliquer la réponse à l'événement
        $event->setResponse($response);
    }
}
