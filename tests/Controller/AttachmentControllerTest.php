<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Attachment\AttachmentStorage;
use App\Attachment\AttachmentUploader;
use App\Entity\Attachment;
use App\Entity\Ticket;
use App\Tests\InterfaceTestCase;
use Imagick;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function strlen;

/**
 * Attachments live outside the web root, so the only way to one is through this
 * controller — and therefore through the firewall it sits behind.
 */
final class AttachmentControllerTest extends InterfaceTestCase
{
    /**
     * @var list<string>
     */
    private array $written = [];

    protected function tearDown(): void
    {
        $storage = static::getContainer()->get(AttachmentStorage::class);

        foreach ($this->written as $path) {
            $storage->removePath($path);
        }

        parent::tearDown();
    }

    public function testAnAnonymousVisitorCannotReachAnAttachment(): void
    {
        $attachment = $this->attach($this->createTicket());

        $this->client->request('GET', '/attachments/'.$attachment->getId());

        self::assertResponseRedirects('/login');
    }

    public function testTheFileComesBackAsADownload(): void
    {
        $attachment = $this->attach($this->createTicket());
        $this->login();

        $this->client->request('GET', '/attachments/'.$attachment->getId());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');

        // Never inline: the stored bytes are not a document this application
        // wants a browser to render inside its own origin.
        self::assertStringStartsWith('attachment;', $disposition);
    }

    /**
     * A PNG that came out as WebP must not be handed back claiming to be a PNG.
     */
    public function testTheDownloadNameCarriesTheStoredFormat(): void
    {
        $attachment = $this->attach($this->createTicket());
        $this->login();

        $this->client->request('GET', '/attachments/'.$attachment->getId());

        self::assertStringContainsString('capture.webp', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testTheBytesServedAreTheBytesStored(): void
    {
        $attachment = $this->attach($this->createTicket());
        $this->login();

        $this->client->request('GET', '/attachments/'.$attachment->getId());

        $body = (string) $this->client->getResponse()->getContent();

        self::assertSame($attachment->getChecksum(), hash('sha256', $body));
        self::assertSame($attachment->getSize(), strlen($body));
    }

    public function testTheTicketScreenListsItsAttachments(): void
    {
        $ticket = $this->createTicket();
        $attachment = $this->attach($ticket);
        $this->login();

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());

        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.attachments a');

        self::assertCount(1, $link);
        self::assertSame('/attachments/'.$attachment->getId(), $link->attr('href'));
        self::assertStringContainsString('capture.png', $link->text());
    }

    /**
     * Attachments are rare by design, so the section is absent rather than shown
     * empty on the tickets that have none — which is nearly all of them.
     */
    public function testATicketWithoutAttachmentsShowsNoSection(): void
    {
        $ticket = $this->createTicket();
        $this->login();

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());

        self::assertCount(0, $crawler->filter('.attachments'));
    }

    public function testAnUnknownAttachmentIsANotFound(): void
    {
        $this->login();

        $this->client->request('GET', '/attachments/999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function attach(Ticket $ticket): Attachment
    {
        $image = new Imagick();
        $image->newPseudoImage(400, 300, 'gradient:red-blue');
        $image->setImageFormat('png');

        $path = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $path, $image->getImagesBlob());

        $attachment = static::getContainer()->get(AttachmentUploader::class)->upload(
            $ticket,
            new UploadedFile((string) $path, 'capture.png', 'image/png', test: true),
            $ticket->getCreatedBy(),
            'Capture of a transient state.',
        );

        $this->written[] = $attachment->getStoragePath();

        return $attachment;
    }
}
