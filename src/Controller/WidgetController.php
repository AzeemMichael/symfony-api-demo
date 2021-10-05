<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Widget;
use App\Form\WidgetType;
use App\Middleware\JwtTokenAuthenticatorMiddleware;
use App\Middleware\ModifyResponseMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Kafkiansky\SymfonyMiddleware\Attribute\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Middleware([ModifyResponseMiddleware::class])]
#[Middleware([JwtTokenAuthenticatorMiddleware::class])]
final class WidgetController extends BaseController
{
    /**
     * @Route("/widgets", name="widgets_new", methods={"POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $widget = new Widget();
        $form = $this->createForm(WidgetType::class, $widget);
        $this->processForm($request, $form);

        if (!$form->isValid()) {
            $this->throwApiProblemValidationException($form);
        }

        $em->persist($widget);
        $em->flush();

        $location = $this->generateUrl('widgets_show', [
            'id' => $widget->getId(),
        ]);

        $response = $this->createApiResponse($widget, Response::HTTP_CREATED);
        $response->headers->set('Location', $location);
        return $response;
    }

    /**
     * @Route("/widgets/{id}", name="widgets_show", methods={"GET"})
     * @param string $id
     * @return Response
     */
    public function show(string $id): Response
    {
        $widget = $this->getDoctrine()
            ->getRepository(Widget::class)
            ->find($id);

        if (!$widget) {
            throw $this->createNotFoundException(sprintf('No widget found for id %s', $id));
        }

        return $this->createApiResponse($widget);
    }

    /**
     * @Route("/widgets", name="widgets_list", methods={"GET"})
     * @return Response
     */
    public function index(): Response
    {
        $widgets = $this->getDoctrine()
            ->getRepository(Widget::class)
            ->findAll();
        return $this->createApiResponse(['widgets' => $widgets]);
    }

    /**
     * @Route("/widgets/{id}", name="widgets_update", methods={"PUT", "PATCH"})
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function update(string $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $widget = $this->getDoctrine()
            ->getRepository(Widget::class)
            ->find($id);

        if (!$widget) {
            throw $this->createNotFoundException(sprintf('No widget found for id %s', $id));
        }

        $form = $this->createForm(WidgetType::class, $widget);
        $this->processForm($request, $form);

        if (!$form->isValid()) {
            $this->throwApiProblemValidationException($form);
        }

        $entityManager->persist($widget);
        $entityManager->flush();

        return $this->createApiResponse($widget);
    }

    /**
     * @Route("/widgets/{id}", name="widgets_delete", methods={"DELETE"})
     * @param string $id
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteAction(string $id, EntityManagerInterface $entityManager): Response
    {
        $widget = $this->getDoctrine()
            ->getRepository(Widget::class)
            ->find($id);

        if ($widget) {
            // debated point: should we 404 on an unknown id?
            // or should we just return a nice 204 in all cases?
            // we're doing the latter
            $entityManager->remove($widget);
            $entityManager->flush();
        }

        return $this->createApiResponse(null, Response::HTTP_NO_CONTENT);
    }
}
