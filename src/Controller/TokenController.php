<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Middleware\ModifyResponseMiddleware;
use Kafkiansky\SymfonyMiddleware\Attribute\Middleware;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

#[Middleware([ModifyResponseMiddleware::class])]
class TokenController extends BaseController
{
    /**
     * @Route("/tokens", name="new_token", methods={"POST"})
     */
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher, JWTEncoderInterface $JWTEncoder)
    {
        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->findOneBy(['email' => $request->getUser()]);

        if (!$user) {
            throw $this->createNotFoundException('No user');
        }

        $isValid = $passwordHasher->isPasswordValid($user, $request->getPassword());

        if (!$isValid) {
            throw new BadCredentialsException();
        }

        $token = $JWTEncoder->encode(['email' => $user->getEmail()]);

        return new JsonResponse([
            'token' => $token
        ]);
    }
}
