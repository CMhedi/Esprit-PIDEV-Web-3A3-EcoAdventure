namespace App\Controller\event;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/event')]
class EventAdminController extends AbstractController
{
    #[Route('/', name: 'admin_event_list')]
    public function index(EvenementRepository $repo): Response
    {
        $events = $repo->findAll();

        return $this->render('admin/event/list.html.twig', [
            'events' => $events
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_event_delete')]
    public function delete(Evenement $event, EntityManagerInterface $em): Response
    {
        $em->remove($event);
        $em->flush();

        return $this->redirectToRoute('admin_event_list');
    }
}