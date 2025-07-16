<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-mailer',
    description: 'Test the mailer configuration',
)]
class TestMailerCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Email recipient')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = $input->getArgument('to');
        
        $email = (new Email())
            ->from('mailer@mailer.de')
            ->to($to)
            ->subject('Test Email')
            ->text('This is a test email to verify the mailer configuration.')
            ->html('<p>This is a test email to verify the mailer configuration.</p>');
        
        try {
            $this->mailer->send($email);
            $output->writeln("<info>Email sent successfully to {$to}</info>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to send email: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}