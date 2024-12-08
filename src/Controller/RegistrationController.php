<?php 

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use App\Repository\UserRepository;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/api/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        tags: ['Registration'],
        summary: 'Register new user',
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['createUser', 'password']))
    )]
    public function register(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, PasswordHasherFactoryInterface $passwordHasherFactory, TagAwareCacheInterface $cache): Response
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasherFactory->getPasswordHasher(User::class)->hash($user->getPassword()));
       
        // Persist here to create a "createdAt" value
        $em->persist($user);
       
        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors[0], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->flush();
        $cache->invalidateTags(['usersCache']);

        // generate a signed url and email it to the user
        try {
            $this->emailVerifier->sendEmailConfirmation('verifyEmail', $user, $this->getParameter('symfony_app_url'), $this->getParameter('target_app_url'),
            (new TemplatedEmail())
                ->from(new Address('mailer@example.com', 'AcmeMailBot'))
                ->to($user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate('registration/confirmation_email.html.twig')
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            return new JsonResponse(
                $serializer->serialize(['message' => $exception->getReason()], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }
        
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_CREATED);
    }


    #[Route('/api/forgot-password', name: 'forgotPassword', methods: ['POST'])]
    #[OA\Post(
        path: '/api/forgot-password',
        tags: ['Registration'],
        summary: 'Forgot password action',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['email']))
    )]
    public function forgotPassword(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $content = $serializer->deserialize($request->getContent(), 'array', 'json');

        if (!isset($content['email'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Email obligatoire"], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $email = $content['email'];
        $user = $userRepository->findOneBy(['email' => $email]);

        // Ensure the user exists in persistence
        if ($user === null) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Ce compte n'existe pas."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // generate a signed url and email it to the user
        try {
            $this->emailVerifier->sendEmailConfirmation('resetPassword', $user, $this->getParameter('symfony_app_url'), $this->getParameter('target_app_url'),
            (new TemplatedEmail())
                ->from(new Address('mailer@example.com', 'AcmeMailBot'))
                ->to($user->getEmail())
                ->subject('Demande de réinitialisation de mot de passe')
                ->htmlTemplate('registration/forgot_password_email.html.twig')
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            return new JsonResponse(
                $serializer->serialize(['message' => $exception->getReason()], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }
        
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }

    #[Route('/api/verify-email', name: 'verifyEmail', methods: ['GET'])]
    #[OA\Get(
        path: '/api/verify-email',
        tags: ['Registration'],
        summary: 'Verify user email address',
        parameters: [
            new OA\Parameter(
                name: "expires",
                description: "Date d'expiration",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "id",
                description: "User ID",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "signature",
                description: "Signature pour vérifier l'authenticité de la requête",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "token",
                description: "Token de vérification",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    public function verifyEmail(Request $request, SerializerInterface $serializer, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id'); // retrieve the user id from the url

        // Verify the user id exists and is not null
        if (null === $id) {
            return new JsonResponse(
                $serializer->serialize([$request->query, 'message' => 'Données manquantes'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user = $userRepository->find($id);

        // Ensure the user exists in persistence
        if (null === $user) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Ce compte n'existe pas."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            return new JsonResponse($serializer->serialize(['message' => $exception->getReason()], 'json', SerializationContext::create()->setGroups(['default'])), 
            JsonResponse::HTTP_BAD_REQUEST, 
            [], 
            true);
        }

        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }

    #[Route('/api/reset-password', name: 'resetPassword', methods: ['POST'])]
    #[OA\Post(
        path: '/api/reset-password',
        tags: ['Registration'],
        summary: 'Reset user password',
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "User ID",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "expires",
                description: "Date d'expiration",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "signature",
                description: "Signature pour vérifier l'authenticité de la requête",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "token",
                description: "Token de vérification",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['password']))
    )]
    public function resetPassword(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UserRepository $userRepository, ValidatorInterface $validator, PasswordHasherFactoryInterface $passwordHasherFactory): Response
    {
        $id = $request->query->get('id'); // retrieve the user id from the url

        // Verify the user id exists and is not null
        if (null === $id) {
            return new JsonResponse(
                $serializer->serialize([$request->query, 'message' => 'Données manquantes'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user = $userRepository->find($id);

        // Ensure the user exists in persistence
        if (null === $user) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Ce compte n'existe pas."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            return new JsonResponse($serializer->serialize(['message' => $exception->getReason()], 'json', SerializationContext::create()->setGroups(['default'])), 
            JsonResponse::HTTP_BAD_REQUEST, 
            [], 
            true);
        }

        $content = $serializer->deserialize($request->getContent(), 'array', 'json');
        
        // If checkOnly => Stop process
        if (isset($content['checkOnly'])) {
            $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
            return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
        }

        if (!isset($content['password'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => 'Mot de passe obligatoire'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user->setPassword($passwordHasherFactory->getPasswordHasher(User::class)->hash($content['password']));

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
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }

    #[Route('/api/resend-validation-email/{id}', name: 'resendValidationEmail', methods: ['GET'])]
    #[OA\Get(
        path: '/api/resend-validation-email/{id}',
        tags: ['Registration'],
        summary: 'Resend a validation email',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User id',
        in: 'path',
    )]
    public function resendValidationEmail(User $user, Request $request, SerializerInterface $serializer): Response
    {
        // Ensure the user exists in persistence
        if (null === $user) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Ce compte n'existe pas."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // generate a signed url and email it to the user
        try {
            $this->emailVerifier->sendEmailConfirmation('verifyEmail', $user, $this->getParameter('symfony_app_url'), $this->getParameter('target_app_url'),
            (new TemplatedEmail())
                ->from(new Address('mailer@example.com', 'AcmeMailBot'))
                ->to($user->getEmail())
                ->subject('Valider votre adresse email')
                ->htmlTemplate('registration/confirmation_email.html.twig')
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            return new JsonResponse(
                $serializer->serialize(['message' => $exception->getReason()], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }
        
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }
}