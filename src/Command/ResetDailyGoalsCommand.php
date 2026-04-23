<?php

namespace App\Command;

use App\Entity\UserApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-daily-goals',
    description: 'Réinitialise goal_notified pour tous les utilisateurs (exécuté chaque jour)',
)]
class ResetDailyGoalsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->em->getRepository(UserApp::class)
            ->createQueryBuilder('u')
            ->update()
            ->set('u.goal_notified', false)
            ->getQuery()
            ->execute();

        $this->em->flush();

        $io->success("✅ $count utilisateurs réinitialisés");

        return Command::SUCCESS;
    }
}