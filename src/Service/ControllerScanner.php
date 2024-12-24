<?php

namespace App\Service;

use App\Attribute\AccessMethods;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Attribute\Description;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/* Récupère la liste des actions des Controllers, sous forme de tableau :
- Si mode=read-write : [Controller => ['description' => '', 'actions' => ['read' => [action1 => ['description' => '', 'action' => ''], action2 => []], 'write' => [action3 => [], action4 => []]]]]
- Si mode=actions : [Controller => ['description' => '', 'actions' => [action1 => ['description' => '', 'action' => ''], action2 => []]]]
 */
class ControllerScanner
{
    private KernelInterface $kernel;
    private ParameterBagInterface $params;
    private TagAwareCacheInterface $cache;

    public function __construct(KernelInterface $kernel, ParameterBagInterface $params, TagAwareCacheInterface $cache)
    {
        $this->kernel = $kernel;
        $this->params = $params;
        $this->cache = $cache;
    }


    /* Essaie de récupérer la liste dans le cache. Si pas disponible, relance l'opération complète */
    public function getControllersAndActions(): array
    {
        $mode = $this->params->get('role_permissions_mode');
        $controllersAndActions = $this->cache->get('getControllersAndActions-' . $mode, function (ItemInterface $item) use ($mode) {
            $item->tag("controllersAndActions");

            $controllers = [];
            $controllerDir = $this->kernel->getProjectDir() . '/src/Controller';
            $namespace = 'App\Controller';

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDir));     

            foreach ($files as $file) {

                if ($file->isFile() && $file->getExtension() === 'php') {
                    $className = $namespace . '\\' . str_replace(
                        '/',
                        '\\',
                        substr($file->getPathname(), strlen($controllerDir) + 1, -4)
                    );

                    if (class_exists($className)) {
                        $reflectionClass = new \ReflectionClass($className);

                        if ($reflectionClass->isSubclassOf('Symfony\Bundle\FrameworkBundle\Controller\AbstractController')) {
                            $controllerDescription = $this->getControllerDescription($reflectionClass);
                            if (!$controllerDescription) continue; // Description de Controller obligatoire pour l'ajouter à la liste

                            $controller = [
                                'description' => $controllerDescription,
                                'actions' => []
                            ];

                            // Si mode lecture/écriture
                            if ($mode === "read-write") {
                                $accessMethods = $this->getAccessMethods($reflectionClass);
                                if (is_null($accessMethods)) continue; // Attribut obligatoire

                                $controller['actions'] = ['read' => [], 'write' => []];
                            }

                            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                                if ($this->isAction($method)) {
                                    $accessMode = null;
                                    // Si mode lecture/écriture
                                    if ($mode === "read-write") {
                                        if (in_array($method->getName(), $accessMethods['write'])) {
                                            $accessMode = 'write';
                                        } else if (in_array($method->getName(), $accessMethods['read'])) {
                                            $accessMode = 'read';
                                        }
                                        if (is_null($accessMode)) continue;
                                    } 

                                    $action = [
                                        'action' => $method->getName(),
                                        'description' => $this->getMethodDescription($method)
                                    ];
                                    if ($accessMode) {
                                        $controller['actions'][$accessMode][] = $action;
                                    } else {
                                        $controller['actions'][] = $action;
                                    }
                                }
                            }
                            if (!empty($controller['actions']) || ($mode === 'read-write' && (!empty($controller['actions']['read']) || !empty($controller['actions']['write'])))) {
                                $controllers[$className] = $controller;
                            } 
                        }
                    }
                }
            }

            return $controllers;
        });

        return $controllersAndActions;
    }


    private function isAction(\ReflectionMethod $method): bool
    {
        // Vérifie si la méthode a l'attribut Symfony\Component\Routing\Annotation\Route
        $attributes = $method->getAttributes(Route::class);

        return !empty($attributes); // Si des attributs #[Route] existent, c'est une action.
    }


    private function getMethodDescription(\ReflectionMethod $method): ?string
    {
        foreach ($method->getAttributes(Description::class) as $attribute) {
            return $attribute->newInstance()->text;
        }

        return null;
    }


    private function getControllerDescription(\ReflectionClass $class): ?string
    {
        foreach ($class->getAttributes(Description::class) as $attribute) {
            return $attribute->newInstance()->text;
        }

        return null;
    }


    private function getAccessMethods(\ReflectionClass $class): ?array
    {
        foreach ($class->getAttributes(AccessMethods::class) as $attribute) {
            $accessMethods = $attribute->newInstance();
            return [
                'read' => $accessMethods->readMethods,
                'write' => $accessMethods->writeMethods,
            ];
        }

        return null;
    }
}

