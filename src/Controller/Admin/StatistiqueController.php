<?php

namespace App\Controller\Admin;

use App\Entity\Evenement;
use App\Entity\ReservationEvenement;
use App\Enum\StatutReservationEvenement;
use App\Repository\EvenementRepository;
use App\Repository\ReservationEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\ColumnChart;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\BarChart;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Service\ReservationPricingService;

#[Route('/admin/statistiques-event')]
class StatistiqueController extends AbstractController
{
    #[Route('/', name: 'app_admin_statistiques_event', methods: ['GET'])]
    public function index(
        EvenementRepository $evenementRepository,
        ReservationEvenementRepository $reservationRepository
    ): Response {
        $events = $evenementRepository->findAll();
        $reservations = $reservationRepository->findAll();

        // 1. Nombre total des événements par mois
        $eventsByMonth = [];
        foreach ($events as $event) {
            $month = $event->getDate_event()->format('Y-m');
            $eventsByMonth[$month] = ($eventsByMonth[$month] ?? 0) + 1;
        }
        ksort($eventsByMonth);

        // 2. Répartition des événements par type
        $eventsByType = [];
        foreach ($events as $event) {
            $type = $event->getCategorie_evt()->value;
            $eventsByType[$type] = ($eventsByType[$type] ?? 0) + 1;
        }

        // 3. Nombre de réservations annulées vs confirmées
        $resStats = [
            'confirmées' => 0,
            'annulées' => 0,
            'en_attente' => 0
        ];
        foreach ($reservations as $res) {
            if ($res->getStatut_res() === StatutReservationEvenement::CONFIRMEE) {
                $resStats['confirmées']++;
            } elseif ($res->getStatut_res() === StatutReservationEvenement::ANNULEE) {
                $resStats['annulées']++;
            } else {
                $resStats['en_attente']++;
            }
        }

        // 4. Taux de remplissage (Percentage mta3 les places elli tba3ou par rapport lel total)
        $totalPlaces = 0;
        $soldPlaces = 0;
        foreach ($events as $event) {
            $totalPlaces += $event->getNb_places();
            $soldPlaces += ($event->getNb_places() - $event->getPlacesRestantes());
        }
        $fillRate = $totalPlaces > 0 ? round(($soldPlaces / $totalPlaces) * 100, 2) : 0;

        // 5. Events les plus populaires (Anahi el catégorie elli 3liha akther demande)
        $demandByType = [];
        foreach ($reservations as $res) {
            if ($res->getStatut_res() !== StatutReservationEvenement::ANNULEE) {
                $type = $res->getEvenement()->getCategorie_evt()->value;
                $demandByType[$type] = ($demandByType[$type] ?? 0) + $res->getNb_billets();
            }
        }
        arsort($demandByType);
        $mostPopularType = !empty($demandByType) ? array_key_first($demandByType) : 'N/A';

        // 6. Revenu estimé (9adech l'admin bech ydakhel flous mel les events elli mazelou)
        $estimatedRevenue = 0;
        foreach ($reservations as $res) {
            if ($res->getStatut_res() === StatutReservationEvenement::CONFIRMEE) {
                $estimatedRevenue += $res->getNb_billets() * $res->getEvenement()->getPrix();
            }
        }

        // --- CMEN Google Charts Integration ---
        
        // Chart 1: Événements par mois (ColumnChart)
        $monthData = [['Mois', 'Événements']];
        foreach ($eventsByMonth as $month => $count) {
            $monthData[] = [$month, $count];
        }

        if (empty($eventsByMonth)) {
            $monthData[] = [(new \DateTime())->format('Y-m'), 0];
        }
        $monthChart = new ColumnChart();
        $monthChart->getData()->setArrayToDataTable($monthData);
        $monthChart->getOptions()->getLegend()->setPosition('none');
        $monthChart->getOptions()->getChartArea()->setWidth('80%');
        $monthChart->getOptions()->getChartArea()->setHeight('70%');
        $monthChart->getOptions()->setColors(['#0d6efd']);
        
        // Chart 2: Répartition par type (PieChart)
        $typeData = [['Catégorie', 'Proportion']];
        foreach ($eventsByType as $type => $count) {
            $typeData[] = [$type, $count];
        }
        $typeChart = new PieChart();
        $typeChart->getData()->setArrayToDataTable($typeData);
        $typeChart->getOptions()->setPieHole(0.5);
        $typeChart->getOptions()->getLegend()->setPosition('bottom');
        $typeChart->getOptions()->setColors(['#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#6610f2', '#fd7e14']);
        
        // Chart 3: Statut des réservations (BarChart ou ColumnChart)
        $resData = [['Statut', 'Nombre', ['role' => 'style']]];
        $resData[] = ['Confirmées', $resStats['confirmées'], 'color: #198754'];
        $resData[] = ['Annulées', $resStats['annulées'], 'color: #dc3545'];
        $resData[] = ['En attente', $resStats['en_attente'], 'color: #ffc107'];
        
        $resChart = new ColumnChart();
        $resChart->getData()->setArrayToDataTable($resData);
        $resChart->getOptions()->getLegend()->setPosition('none');
        $resChart->getOptions()->getChartArea()->setWidth('80%');
        $resChart->getOptions()->getChartArea()->setHeight('70%');

        return $this->render('admin/event/statistiques.html.twig', [
            'monthChart' => $monthChart,
            'typeChart' => $typeChart,
            'resChart' => $resChart,
            'fillRate' => $fillRate,
            'mostPopularType' => $mostPopularType,
            'estimatedRevenue' => $estimatedRevenue,
            'totalEvents' => count($events),
            'totalReservations' => count($reservations),
            'resStats' => $resStats
        ]);
    }

    #[Route('/export-excel', name: 'app_admin_statistiques_export_excel', methods: ['GET'])]
    public function exportExcel(EvenementRepository $evenementRepository, ReservationPricingService $pricingService, \App\Service\AiEventOptimizerService $aiOptimizer): Response
    {
        $events = $evenementRepository->findAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport Financier');

        // Style Arrays
        $headerStyleArray = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '198754']], // Vert EcoAdventure
        ];

        // 1. Titre Principal
        $sheet->mergeCells('A1:K2');
        $sheet->setCellValue('A1', 'RAPPORT FINANCIER DÉTAILLÉ DES ÉVÉNEMENTS 🌿 ECOADVENTURE');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1a4731']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e8f5e9']]
        ]);

        // Date de génération
        $sheet->mergeCells('A3:K3');
        $sheet->setCellValue('A3', 'Généré par l\'Administrateur le : ' . (new \DateTime())->format('d/m/Y à H:i'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('888888'));

        // 2. En-têtes des colonnes
        $headers = [
            'ID', 'Titre de l\'événement', 'Catégorie', 'Date & Heure', 
            'Capacité Max', 'Prix Unit. (DT)', 'Billets Confirmés', 
            'Billets Annulés', 'Billets En Attente', 'Remises (Promos)', 'Revenu Net (DT)'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '5', $header);
            $col++;
        }
        $sheet->getStyle('A5:K5')->applyFromArray($headerStyleArray);
        $sheet->getRowDimension(5)->setRowHeight(25);

        $row = 6;
        $totalRevenuNetGlobal = 0;
        $totalBilletsVendusGlobal = 0;
        $totalRemisesGlobal = 0;
        $totalAnnulesGlobal = 0;

        foreach ($events as $event) {
            $billetsConfirmes = 0;
            $billetsAnnules = 0;
            $billetsAttente = 0;
            $revenuNetEvent = 0;
            $remisesEvent = 0;

            // Calcul Métier Ultime pour le CA Net réel (Prend en compte le Pricing Service "Groupe")
            foreach ($event->getReservationEvenements() as $res) {
                $status = $res->getStatut_res();
                
                if ($status === StatutReservationEvenement::CONFIRMEE) {
                    $billetsConfirmes += $res->getNb_billets();
                    // On recalcule le montant net qui a réellement été encaissé après la remise potentielle
                    $pricing = $pricingService->calculatePricing($event, $res->getNb_billets());
                    $revenuNetEvent += $pricing['totalFinal'];
                    $remisesEvent += $pricing['remise'];
                } elseif ($status === StatutReservationEvenement::ANNULEE) {
                    $billetsAnnules += $res->getNb_billets();
                } else {
                    $billetsAttente += $res->getNb_billets();
                }
            }

            $totalRevenuNetGlobal += $revenuNetEvent;
            $totalBilletsVendusGlobal += $billetsConfirmes;
            $totalRemisesGlobal += $remisesEvent;
            $totalAnnulesGlobal += $billetsAnnules;

            $sheet->setCellValue('A' . $row, $event->getId_evenement());
            $sheet->setCellValue('B' . $row, $event->getTitre());
            $sheet->setCellValue('C' . $row, $event->getCategorie_evt()->value);
            $sheet->setCellValue('D' . $row, $event->getDate_event()->format('d/m/Y H:i'));
            $sheet->setCellValue('E' . $row, $event->getNb_places());
            $sheet->setCellValue('F' . $row, $event->getPrix());
            $sheet->setCellValue('G' . $row, $billetsConfirmes);
            $sheet->setCellValue('H' . $row, $billetsAnnules);
            $sheet->setCellValue('I' . $row, $billetsAttente);
            $sheet->setCellValue('J' . $row, $remisesEvent);
            $sheet->setCellValue('K' . $row, $revenuNetEvent);
            
            // Format monétaire
            $sheet->getStyle("F{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0.00 "DT"');
            $sheet->getStyle("J{$row}:K{$row}")->getNumberFormat()->setFormatCode('#,##0.00 "DT"');

            // Style de ligne : rayures (Zebra stripes)
            $bgColor = ($row % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'DDDDDD']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
            ]);

            // Mises en valeur par couleur (Vert pour confirmé, Rouge pour annulé, Gras pour le CA)
            $sheet->getStyle("G{$row}")->getFont()->getColor()->setRGB('198754');
            $sheet->getStyle("G{$row}")->getFont()->setBold(true);
            
            if ($billetsAnnules > 0) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('DC3545');
            }
            if ($remisesEvent > 0) {
                $sheet->getStyle("J{$row}")->getFont()->getColor()->setRGB('fd7e14'); // Orange / Warning pour l'argent perdu en promo
            }
            $sheet->getStyle("K{$row}")->getFont()->setBold(true);

            $row++;
        }

        // --- Ligne des TOTAUX GLOBAUX ---
        $row++; // Espace avant total
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAUX GLOBAUX SUR TOUS LES ÉVÉNEMENTS :');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $sheet->setCellValue("G{$row}", $totalBilletsVendusGlobal);
        $sheet->setCellValue("H{$row}", $totalAnnulesGlobal);
        $sheet->setCellValue("J{$row}", $totalRemisesGlobal);
        $sheet->setCellValue("K{$row}", $totalRevenuNetGlobal);

        // Styling Ultra-Pro pour la ligne Totaux
        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D1B2A']], // Dark Blue
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ]);
        $sheet->getStyle("J{$row}:K{$row}")->getNumberFormat()->setFormatCode('#,##0.00 "DT"');
        $sheet->getRowDimension($row)->setRowHeight(30);

        // Ajustement automatique de TOUTES les largeurs de colonnes selon le contenu
        foreach (range('A', 'K') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Freeze Title Rows
        $sheet->freezePane('A6');

        // =========================================================================
        // DEUXIÈME ONGLET : PRÉDICTIONS INTELLIGENCE ARTIFICIELLE / MACHINE LEARNING
        // =========================================================================
        $mlSheet = $spreadsheet->createSheet();
        $mlSheet->setTitle('Prédictions IA 2026-2027');

        $mlSheet->mergeCells('A1:G2');
        $mlSheet->setCellValue('A1', '🧠 ALGORITHME DE PRÉDICTION ANNUELLE (MACHINE LEARNING)');
        $mlSheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '023E8A']] 
        ]);

        $mlSheet->mergeCells('A3:G3');
        $mlSheet->setCellValue('A3', 'Basé sur l\'analyse prédictive des données de remplissage et de saisonnalité actuelles.');
        $mlSheet->getStyle('A3')->getFont()->setItalic(true);

        $mlHeaders = [
            'ID Event', 'Événement', 'Catégorie', 'Taux Remplissage Actuel',
            'Score Popularité (IA)', 'Prévision Billets (1 An)', 'Chiffre d\'Affaires Estimé (1 An) DT'
        ];
        
        $col = 'A';
        foreach ($mlHeaders as $header) {
            $mlSheet->setCellValue($col . '5', $header);
            $col++;
        }
        $mlSheet->getStyle('A5:G5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0096C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $mlSheet->getRowDimension(5)->setRowHeight(20);

        $rowML = 6;
        $totalCAAnnee = 0;
        $totalBilletsAnnee = 0;

        foreach ($events as $event) {
            // 🤖 APPEL À L'AGENT IA POUR LES PRÉDICTIONS (FINI LE PHP !)
            $aiPrediction = $aiOptimizer->predictFinancials($event);
            
            $scoreIA = (int) $aiPrediction['score_ia'];
            $predictedTickets = (int) $aiPrediction['predicted_tickets'];
            $predictedCA = (float) $aiPrediction['predicted_ca'];

            // Calcul du taux actuel pour l'affichage
            $placesMax = $event->getNb_places();
            $tauxRemplissage = $placesMax > 0 ? (max(0, $placesMax - $event->getPlacesRestantes()) / $placesMax) : 0;

            $totalBilletsAnnee += $predictedTickets;
            $totalCAAnnee += $predictedCA;

            $mlSheet->setCellValue('A' . $rowML, $event->getId_evenement());
            $mlSheet->setCellValue('B' . $rowML, $event->getTitre());
            $mlSheet->setCellValue('C' . $rowML, $event->getCategorie_evt()->value);
            $mlSheet->setCellValue('D' . $rowML, round($tauxRemplissage * 100, 1) . ' %');
            $mlSheet->setCellValue('E' . $rowML, $scoreIA . '/100');
            $mlSheet->setCellValue('F' . $rowML, $predictedTickets);
            $mlSheet->setCellValue('G' . $rowML, $predictedCA);

            $mlSheet->getStyle("E{$rowML}")->getFont()->setBold(true)->getColor()->setRGB('0077B6');
            $mlSheet->getStyle("G{$rowML}")->getNumberFormat()->setFormatCode('#,##0.00 "DT"');
            
            $rowML++;
        }

        // Ligne de Conclusion Totale IA
        $rowML++;
        $mlSheet->mergeCells("A{$rowML}:E{$rowML}");
        $mlSheet->setCellValue("A{$rowML}", 'CA GLOBAL PRÉDIT PAR IA POUR L\'ANNÉE PROCHAINE :');
        $mlSheet->getStyle("A{$rowML}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $mlSheet->setCellValue("F{$rowML}", $totalBilletsAnnee);
        $mlSheet->setCellValue("G{$rowML}", $totalCAAnnee);

        $mlSheet->getStyle("A{$rowML}:G{$rowML}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '03045E']]
        ]);
        $mlSheet->getStyle("G{$rowML}")->getNumberFormat()->setFormatCode('#,##0.00 "DT"');

        foreach (range('A', 'G') as $columnID) {
            $mlSheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Remettre l'onglet principal actif
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="Rapport_Financier_EcoAdventure.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0'); // Disable parsing by browser cache

        return $response;
    }
}
