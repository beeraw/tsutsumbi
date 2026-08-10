<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PreferenceController;
use App\Tests\InterfaceTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PreferenceControllerTest extends InterfaceTestCase
{
    /**
     * The form path, for a reader without scripting: the preference is stored
     * and the page they were on is rebuilt in the new state.
     */
    public function testPostingTheFormStoresThePreferenceAndComesBack(): void
    {
        $this->login();

        $this->client->request('POST', '/preferences/rail', [
            '_token' => $this->railToken(),
            'value' => 'narrow',
            'from' => '/',
        ]);

        self::assertResponseRedirects('/');
        self::assertSame('narrow', $this->storedRail());
    }

    /**
     * The scripted path: the fold is already on screen, so there is no page to
     * send back — only the cookie is worth the round trip.
     */
    public function testABackgroundCallStoresThePreferenceWithoutAPage(): void
    {
        $this->login();

        $this->client->xmlHttpRequest('POST', '/preferences/rail', [
            '_token' => $this->railToken(),
            'value' => 'wide',
            'from' => '/',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertEmpty((string) $this->client->getResponse()->getContent());
        self::assertSame('wide', $this->storedRail());
    }

    /**
     * Both sides of each toggle ship on every page: the state is changed on the
     * client without rebuilding anything, so the button that is about to be
     * revealed has to already be in the document.
     */
    public function testEveryToggleShipsBothOfItsButtons(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/');

        self::assertCount(1, $crawler->filter('.rail-toggle-collapse'));
        self::assertCount(1, $crawler->filter('.rail-toggle-expand'));
        self::assertCount(1, $crawler->filter('.theme-to-dark'));
        self::assertCount(1, $crawler->filter('.theme-to-light'));
    }

    public function testAnUnknownValueIsRefused(): void
    {
        $this->login();

        $this->client->xmlHttpRequest('POST', '/preferences/rail', [
            '_token' => $this->railToken(),
            'value' => 'sideways',
            'from' => '/',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function railToken(): string
    {
        $crawler = $this->client->request('GET', '/');

        return (string) $crawler->filter('[data-rail-form] input[name="_token"]')->attr('value');
    }

    private function storedRail(): ?string
    {
        $cookie = $this->client->getCookieJar()->get(PreferenceController::RAIL_COOKIE);

        return $cookie?->getValue();
    }
}
