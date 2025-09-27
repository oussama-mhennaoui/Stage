<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-unverified-accounts',
    description: 'Removes unverified user accounts that are older than the specified number of days',
)]
class CleanupUnverifiedAccountsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private int $days = 7; // Default: 7 days

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to remove unverified user accounts that are older than the specified number of days.')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days after which unverified accounts should be deleted',
                $this->days
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run without making any changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry run enabled - no changes will be made');
        }

        $io->title(sprintf('Cleaning up unverified accounts older than %d days', $this->days));

        // Find unverified accounts older than the specified number of days
        $unverifiedUsers = $this->userRepository->findUnverifiedOlderThan($this->days);
        $count = count($unverifiedUsers);

        if ($count === 0) {
            $io->success('No unverified accounts found that are older than the specified period.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Found %d unverified account(s) older than %d days', $count, $this->days));

        $confirmation = $io->confirm(
            sprintf(
                'Are you sure you want to delete %d unverified account(s) that are older than %d days?',
                $count,
                $this->days
            ),
            false
        );

        if (!$confirmation) {
            $io->note('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Process each unverified user
        $deleted = 0;
        $skipped = 0;

        foreach ($unverifiedUsers as $user) {
            $email = $user->getEmail();
            $createdAt = $user->getCreatedAt() ? $user->getCreatedAt()->format('Y-m-d H:i:s') : 'unknown';
            
            if ($dryRun) {
                $io->writeln(sprintf(
                    '[DRY RUN] Would delete unverified account: %s (created at: %s)',
                    $email,
                    $createdAt
                ));
                $deleted++;
                continue;
            }

            try {
                $this->entityManager->remove($user);
                $this->entityManager->flush();
                
                $io->writeln(sprintf(
                    'Deleted unverified account: %s (created at: %s)',
                    $email,
                    $createdAt
                ));
                
                $this->logger->info('Deleted unverified account', [
                    'email' => $email,
                    'created_at' => $createdAt,
                    'deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ]);
                
                $deleted++;
            } catch (\Exception $e) {
                $io->error(sprintf('Error deleting account %s: %s', $email, $e->getMessage()));
                $this->logger->error('Error deleting unverified account', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $skipped++;
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Cleanup complete. Deleted: %d, Skipped: %d',
            $deleted,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
