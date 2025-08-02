<?php

namespace App\Controller;

use App\Repository\NotesRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted("ROLE_ADMIN")]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[Route('/dashboard/statistics', name: 'dashboard_notes_stats')]
public function notesStatistics(NotesRepository $notesRepository): JsonResponse
{
    // Get raw statistics data from repository
    $monthlyStats = $notesRepository->getMonthlyStats($this->getUser());

    // Transform data for Chart.js
    $chartData = [
        'labels' => array_column($monthlyStats, 'month'),
        'datasets' => [
            [
                'label' => 'Notes Created',
                'data' => array_column($monthlyStats, 'created'),
                'backgroundColor' => 'rgba(58, 138, 192, 0.1)',
                'borderColor' => 'rgba(58, 138, 192, 1)',
                'borderWidth' => 2
            ],
            [
                'label' => 'Notes Burned',
                'data' => array_column($monthlyStats, 'burned'),
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                'borderColor' => 'rgba(239, 68, 68, 1)',
                'borderWidth' => 2
            ]
        ]
    ];

    return new JsonResponse($chartData);
}
        
}
