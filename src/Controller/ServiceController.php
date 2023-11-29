<?php

namespace App\Controller;

use App\Entity\Service;
use App\Form\servicemodiftype;
use App\Form\ServiceType;
use App\Repository\OrdersRepository;
use App\Repository\ServiceRepository;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Endroid\QrCodeBundle\Response\QrCodeResponse;

#[Route('/service')]
class ServiceController extends AbstractController
{

    #[Route('/', name: 'app_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        return $this->render('service/index.html.twig', [
            'services' => $serviceRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('img')->getData();

            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('images_directory'), // Set your actual images directory
                    $newFilename
                );
                $service->setImg($newFilename);
            }

            $entityManager->persist($service);
            $entityManager->flush();

            return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('service/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/edit/{serviceid}', name: 'app_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $oldImg = $service->getImg(); // Get the existing image filename

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('img')->getData();

            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('images_directory'), // Set your actual images directory
                    $newFilename
                );
                $service->setImg($newFilename);
            } else {
                // If no new image is provided, keep the old image
                $service->setImg($oldImg);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('service/edit.html.twig', [
            'service' => $service,
            'form' => $form,
            'oldImg' => $oldImg, // Pass the old image filename to the template
        ]);
    }


    #[Route('/{serviceid}', name: 'app_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $service->getServiceid(), $request->request->get('_token'))) {
            $entityManager->remove($service);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/show/{serviceid}', name: 'app_service_show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/details/{serviceid}', name: 'app_orders_show_details', methods: ['GET'])]
    public function showdetails(Service $service): Response
    {
        return $this->render('service/show_details.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/shop', name: 'app_service_shop', methods: ['GET', 'POST'])]
    public function homeshop(        OrdersRepository $ordersRepository,
                                     Request $request, EntityManagerInterface $entityManager): Response
    {
        $sumOfPaidOrders = $ordersRepository->calculateSumOfPaidOrders();

        // Fetch services from the database
        $services = $this->getDoctrine()->getRepository(Service::class)->findAll();
        $qrCode=0;
        return $this->render('service/ServicesShop.html.twig', [
            'qrCode' => $qrCode,

            'services' => $services,
            'sumOfPaidOrders' => $sumOfPaidOrders,
        ]);
    }

    #[Route("/search", name: "search", methods: ['GET'])]
    public function search(Request $request)
    {
        $searchTerm = $request->query->get('search_term');

        // Call the searchByName method from your repository or service
        $services = $this->getDoctrine()->getRepository(Service::class)->searchByName($searchTerm);
        return $this->render('service/ServicesShop.html.twig', [
            'services' => $services,

        ]);

    }


    #[Route('/qrcode', name: 'qrcode', methods: ['GET', 'POST'])]
    public function checkQrCode(
        OrdersRepository $ordersRepository,
        ServiceRepository $serviceRepository,
        QrcodeService $qrcodeService
    ): Response {
        $sumOfPaidOrders = $ordersRepository->calculateSumOfPaidOrders();

        if ($sumOfPaidOrders > 40) {
            // Generate QR code content (customize this based on your requirements)
            $qrCodeContent = 'ecofriendlyesprit';

            // Generate QR code using the QrCodeService
            $qrCode = $qrcodeService->qrCode($qrCodeContent);

            // Pass the QR code to the template or use it as needed
            return $this->render('service/ServicesShop.html.twig', [
                'qrCode' => $qrCode,
                'sumOfPaidOrders' => $sumOfPaidOrders,
                'services' => $serviceRepository->findAll(),
            ]);
        }

        // Handle the case when the sum of paid orders is not greater than 40
        return $this->render('service/ServicesShop.html.twig', [
            'sumOfPaidOrders' => $sumOfPaidOrders,
            'services' => $serviceRepository->findAll(),
        ]);
    }
}