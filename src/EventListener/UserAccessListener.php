<?php

namespace App\EventListener;


use App\Security\AccessChecker;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


class UserAccessListener
{
    private AccessChecker $accessChecker;


    public function __construct(AccessChecker $accessChecker)
    {
        $this->accessChecker = $accessChecker;
    }


    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        // Si le contrôleur est une classe
        if (is_array($controller)) {
            $controllerClass = get_class($controller[0]);
            $action = $controller[1];

            if (!$this->accessChecker->isActionAuthorized($controllerClass, $action) && !$this->accessChecker->isAllowed($controllerClass, $action)) {
                $event->setController(function () {
                    return new JsonResponse(['error' => 'Access Denied'], Response::HTTP_FORBIDDEN);
                });
            }
        }
    }
}

