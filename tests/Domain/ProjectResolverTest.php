<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\ProjectResolver;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;
use function strlen;

/**
 * resolve() is the pivot every agent session starts with, so idempotence is the
 * property that matters most in the whole codebase.
 */
final class ProjectResolverTest extends KernelTestCase
{
    private ProjectResolver $resolver;
    private ProjectRepository $projects;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->resolver = self::getContainer()->get(ProjectResolver::class);
        $this->projects = self::getContainer()->get(ProjectRepository::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTheFirstCallCreatesTheProjectAndTheSecondFindsIt(): void
    {
        $path = $this->path('maji');

        $first = $this->resolver->resolve($path, 'Maji');
        $second = $this->resolver->resolve($path, 'Maji');

        self::assertTrue($first->created);
        self::assertFalse($second->created);
        self::assertSame($first->project->getId(), $second->project->getId());
    }

    public function testEverySpellingOfTheSamePathLandsOnTheSameProject(): void
    {
        $path = $this->path('maji');

        $canonical = $this->resolver->resolve($path)->project;

        foreach ([$path.'/', $path.'/.', str_replace('/', '//', $path), $path.'/sub/..'] as $variant) {
            self::assertSame(
                $canonical->getId(),
                $this->resolver->resolve($variant)->project->getId(),
                sprintf('"%s" should resolve to the same project.', $variant),
            );
        }
    }

    public function testTenCallsInARowCreateExactlyOneProject(): void
    {
        $path = $this->path('maji');

        for ($i = 0; $i < 10; ++$i) {
            $this->resolver->resolve($path, 'Maji');
        }

        self::assertCount(1, $this->projects->findBy(['path' => $path]));
    }

    public function testTheKeyIsDerivedFromTheDirectoryName(): void
    {
        self::assertSame('MAJI', $this->resolver->resolve($this->path('maji'))->project->getKey());
    }

    public function testNonAlphanumericCharactersAreDroppedFromTheKey(): void
    {
        self::assertSame('KDSDOUBLE', $this->resolver->resolve($this->path('kds.double'))->project->getKey());
    }

    public function testTheKeyIsTruncatedToTenCharacters(): void
    {
        $key = $this->resolver->resolve($this->path('averylongprojectname'))->project->getKey();

        self::assertSame('AVERYLONGP', $key);
    }

    public function testACollidingKeyGetsANumericSuffix(): void
    {
        $first = $this->resolver->resolve('/first/maji')->project;
        $second = $this->resolver->resolve('/second/maji')->project;
        $third = $this->resolver->resolve('/third/maji')->project;

        self::assertSame('MAJI', $first->getKey());
        self::assertSame('MAJI2', $second->getKey());
        self::assertSame('MAJI3', $third->getKey());
    }

    public function testTheSuffixStaysInsideTheKeyLength(): void
    {
        $this->resolver->resolve('/first/averylongprojectname');
        $second = $this->resolver->resolve('/second/averylongprojectname')->project;

        self::assertSame('AVERYLONG2', $second->getKey());
        self::assertLessThanOrEqual(10, strlen($second->getKey()));
    }

    public function testTheDirectoryNameIsUsedWhenNoNameIsGiven(): void
    {
        self::assertSame('maji', $this->resolver->resolve($this->path('maji'))->project->getName());
    }

    /**
     * An agent replaying its session start must not undo a rename made from the
     * interface, so resolve() reads or creates — it never updates.
     */
    public function testResolvingAgainDoesNotRenameTheProject(): void
    {
        $path = $this->path('maji');

        $this->resolver->resolve($path, 'Maji');
        $this->entityManager->flush();

        $second = $this->resolver->resolve($path, 'Something Else');

        self::assertSame('Maji', $second->project->getName());
    }

    private function path(string $name): string
    {
        // The random part goes in a parent directory: the last segment is what
        // the project key is derived from, and the tests assert on that key.
        return sprintf('/tmp/tsutsumbi-tests/%s/%s', bin2hex(random_bytes(4)), $name);
    }
}
