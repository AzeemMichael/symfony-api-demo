<?php declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiProblem;
use App\Api\ApiProblemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

abstract class BaseController extends AbstractController
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    protected function createApiResponse($data, int $statusCode = Response::HTTP_OK, string $format = 'json'): Response
    {
        $json = $this->serialize($data, $format);

        return new Response($json, $statusCode, [
            'Content-Type' => 'application/json'
        ]);
    }

    protected function serialize(mixed $data, string $format = 'json'): string
    {
        return $this->serializer->serialize($data, $format);
    }

    protected function processForm(Request $request, FormInterface $form): void
    {
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            $apiProblem = new ApiProblem(
                Response::HTTP_BAD_REQUEST,
                ApiProblem::TYPE_INVALID_REQUEST_BODY_FORMAT
            );

            throw new ApiProblemException($apiProblem);
        }

        $clearMissing = $request->getMethod() !== Request::METHOD_PATCH;
        $form->submit($data, $clearMissing);
    }

    protected function getErrorsFromForm(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    protected function throwApiProblemValidationException(FormInterface $form): void
    {
        $errors = $this->getErrorsFromForm($form);
        $apiProblem = new ApiProblem(
            Response::HTTP_BAD_REQUEST,
            ApiProblem::TYPE_VALIDATION_ERROR
        );
        $apiProblem->set('errors', $errors);
        throw new ApiProblemException($apiProblem);
    }
}
