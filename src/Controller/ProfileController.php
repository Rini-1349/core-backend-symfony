<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileController extends AbstractController
{
    #[Route('/api/profile', name: 'getProfile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile',
        tags: ['Profile'],
        summary: 'Get profile',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            ref: new Model(type: User::class, groups: ['getUser'])
                        )
                    ]
                )
            )
        ]
    )]
    public function getProfile(SecurityBundleSecurity $security, SerializerInterface $serializer): JsonResponse
    {
        $user = $security?->getUser();
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['profile']));

        $responseContent = [
            'message' => "Informations du profil récupérées.",
            'data' => json_decode($jsonUser, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }


    #[Route('/api/profile', name: 'updateProfile', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/profile',
        tags: ['Profile'],
        summary: 'Update profile',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            ref: new Model(type: User::class, groups: ['getUser'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['profile']))
    )]
    public function updateProfile(SecurityBundleSecurity $security, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $currentUser = $security?->getUser();

        $updatedUser = $serializer->deserialize(
            $request->getContent(), 
            User::class, 
            'json'
        );

        foreach (['lastname', 'firstname', 'email'] as $field) {
            $setter = 'set' . ucfirst($field);
            $getter = 'get' . ucfirst($field);
            $currentUser->$setter($updatedUser->$getter());
        }

        $errors = $validator->validate($currentUser);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($currentUser);
        $em->flush();
        $cache->invalidateTags(['usersCache']);

        $jsonUser = $serializer->serialize($currentUser, 'json', SerializationContext::create()->setGroups(['profile']));
        
        $responseContent = [
            'message' => "Profil mis à jour",
            'data' => json_decode($jsonUser, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }


    #[Route('/api/profile/edit-password', name: 'editProfilePassword', methods: ['POST'])]
    #[OA\Post(
        path: '/api/profile/edit-password',
        tags: ['Profile'],
        summary: 'Edit profile password',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            ref: new Model(type: User::class, groups: ['getUser'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'currentPassword', type: 'string', description: 'Ancien mot de passe'),
                new OA\Property(property: 'newPassword', type: 'string', description: 'Nouveau mot de passe')
            ]
        )
    )]
    public function editProfilePassword(SecurityBundleSecurity $security, UserRepository $userRepository, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher): Response
    {
        $loggedUser = $security?->getUser();
        
        if (!$loggedUser) {
            return new JsonResponse(
                $serializer->serialize(['message' => 'Problème lors de la récupération des informations de votre compte'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user = $userRepository->findOneByEmail($loggedUser->getUserIdentifier());

        $content = $serializer->deserialize($request->getContent(), 'array', 'json');

        if (!isset($content['currentPassword']) || !isset($content['newPassword'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => 'Ancien et nouveau mots de passe obligatoires'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        if (!$passwordHasher->isPasswordValid($user, $content['currentPassword'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => 'Ancien mot de passe incorrect'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user->setPassword($passwordHasher->hashPassword($user, $content['newPassword']));

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors[0], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        $responseContent = [
            'message' => "Mot de passe mis à jour.",
            'data' => json_decode($jsonUser, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }
}
