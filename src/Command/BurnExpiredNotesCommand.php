<?php

namespace App\Command;

use App\Entity\Notes;
use App\Repository\LogsRepository;
use App\Repository\NotesRepository;
use App\Service\SecureEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AttachementsRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Symfony console command to burn all expired notes.
 *
 * This command searches for all notes whose expiration date has passed and
 * which are not yet burned, then performs the burning process. Burning involves
 * marking the note as burned, masking its content and title, setting the deletion
 * date, updating the related logs, and deleting all associated attachments both
 * from the filesystem and the database.
 *
 * Usage:
 *   php bin/console app:burn-expired-notes
 */
#[AsCommand(
    name: 'app:burn-expired-notes',
    description: 'Burn all notes whose expiration date has passed, including deleting attachments'
)]
class BurnExpiredNotesCommand extends Command
{
    /** 
     * The name of the command (the part after "bin/console"). 
     * 
     * @var string
     */
    protected static $defaultName = 'app:burn-expired-notes';

    /** 
     * A short description of what the command does. 
     * 
     * @var string
     */
    protected static $defaultDescription = 'Burn all notes whose expiration date has passed, including deleting attachments';

    /**
     * Notes repository for querying notes.
     */
    private readonly NotesRepository $notesRepository;

    /**
     * Logs repository for updating note logs.
     */
    private readonly LogsRepository $logsRepository;

    /**
     * Attachments repository for fetching attachments linked to notes.
     */
    private readonly AttachementsRepository $attachmentsRepository;

    /**
     * Entity Manager for database operations.
     */
    private readonly EntityManagerInterface $entityManager;

    /**
     * Filesystem service to manage file operations.
     */
    private readonly Filesystem $filesystem;

    /**
     * Translator service for translating messages.
     */
    private readonly TranslatorInterface $translator;

    /**
     * Path to the directory where note attachments are stored.
     */
    private readonly string $attachmentsDirectory;

    /**
     * Decrypt Title note
     */
    private readonly SecureEncryptionService $encryptionService;

    /**
     * Constructor.
     *
     * @param NotesRepository $notesRepository
     * @param LogsRepository $logsRepository
     * @param AttachementsRepository $attachmentsRepository
     * @param EntityManagerInterface $entityManager
     * @param Filesystem $filesystem
     * @param TranslatorInterface $translator
     * @param SecureEncryptionService $encryptionService
     * @param string $attachmentsDirectory Absolute path to attachments directory
     */
    public function __construct(
        NotesRepository $notesRepository,
        LogsRepository $logsRepository,
        AttachementsRepository $attachmentsRepository,
        EntityManagerInterface $entityManager,
        Filesystem $filesystem,
        TranslatorInterface $translator,
        ParameterBagInterface $params,
        SecureEncryptionService $encryptionService
    ) {
        parent::__construct();
        $this->notesRepository = $notesRepository;
        $this->logsRepository = $logsRepository;
        $this->attachmentsRepository = $attachmentsRepository;
        $this->entityManager = $entityManager;
        $this->filesystem = $filesystem;
        $this->translator = $translator;
        $this->attachmentsDirectory = $params->get('notes_attachments_directory');
        $this->encryptionService = $encryptionService;
     
    }

    /**
     * Executes the command.
     *
     * Finds all notes expired before current GMT datetime and not burned,
     * burns each note by masking content, deleting attachments and updating logs,
     * then persists all changes.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command exit status
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new \DateTime('now', new \DateTimeZone('GMT'));

       

        $expiredNotes = $this->notesRepository->createQueryBuilder('n')
            ->andWhere('n.expirationDate IS NOT NULL')
            ->andWhere('n.expirationDate < :now')
            ->andWhere('n.burned IS NULL OR n.burned <> true')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
      
        if (count($expiredNotes) === 0) {
            $io->success('No expired notes to burn.');
            return Command::SUCCESS;
        }

        foreach ($expiredNotes as $note) {
            $this->burnNote($note);
            $io->writeln(sprintf('Note "%s" burned.', $note->getSlug()));
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully burned %d expired notes.', count($expiredNotes)));

        return Command::SUCCESS;
    }

    /**
     * Burns a single note.
     *
     * This involves marking it as burned, masking content and title,
     * setting deletion date, updating the associated log and deleting
     * all attachments (files and database records).
     *
     * @param Notes $note The note entity to burn
     */
    private function burnNote(Notes $note): void
    {

        // Decrypt Title Note to add In logs
        $aad = $note->getEncryptionMetadata();
        try {
            $decryptedTitle = $this->encryptionService->decrypt($note->getTitle(), $aad);
        } catch (\Exception $e) {
           
            $decryptedTitle = '[Titre non déchiffrable]';
        }
        $note->setBurned(true);
        $note->setContent('**********');
        $note->setTitle('**********');
        $note->setDeletedAt(new \DateTime('now', new \DateTimeZone('GMT')));

        $log = $this->logsRepository->findOneBy(['note' => $note]);
        if ($log) {
            $log->setDeletedAt(new \DateTime('now', new \DateTimeZone('GMT')));
            $log->setNoteTitle($decryptedTitle);
            $log->setAdditionnalData($this->translator->trans('Permanently removed due to expiration date.'));
          
        }

        $this->deleteAllAttachmentsForNote($note);
    }

    /**
     * Deletes all attachment files and database records related to a note.
     *
     * @param Notes $note The note whose attachments should be deleted
     */
    private function deleteAllAttachmentsForNote(Notes $note): void
    {
        $attachments = $this->attachmentsRepository->findBy(['note' => $note]);

        foreach ($attachments as $attachment) {
            $filePath = $this->attachmentsDirectory . '/' . $attachment->getFilepath();

            if ($this->filesystem->exists($filePath)) {
                $this->filesystem->remove($filePath);
            }

            $this->entityManager->remove($attachment);
        }
    }
}
