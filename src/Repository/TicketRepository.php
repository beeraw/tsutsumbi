<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\TicketKey;
use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Search\TicketCriteria;
use App\Search\TicketPage;
use App\Search\TicketSort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function count;
use function sprintf;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findOneByKey(string $key): ?Ticket
    {
        $parsed = TicketKey::tryParse($key);

        if (null === $parsed) {
            return null;
        }

        return $this->createQueryBuilder('t')
            ->addSelect('p')
            ->join('t.project', 'p')
            ->andWhere('p.key = :projectKey')
            ->andWhere('t.number = :number')
            ->setParameter('projectKey', $parsed->projectKey)
            ->setParameter('number', $parsed->number)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Everything still open on a project. This is what resolve() hands back to
     * prime an agent, so it must be cheap and short.
     *
     * @return list<Ticket>
     */
    public function findActiveByProject(Project $project, int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('p')
            ->join('t.project', 'p')
            ->andWhere('t.project = :project')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('statuses', TicketStatus::activeValues())
            ->orderBy('t.updatedAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countActiveByProject(Project $project): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.project = :project')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('statuses', TicketStatus::activeValues())
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function search(TicketCriteria $criteria): TicketPage
    {
        $qb = $this->createCriteriaQueryBuilder($criteria);

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $this->applyOrder($qb, $criteria);

        /** @var list<Ticket> $items */
        $items = $qb
            ->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult()
        ;

        return new TicketPage($items, $total, $criteria->page, $criteria->perPage);
    }

    /**
     * The listing's order, and the tie-breaker that keeps paging honest.
     *
     * Without that last clause a page boundary falling inside a group of equal
     * values — three tickets of the same type, a hundred with the same status —
     * lets the database return them in whatever order it likes, and a row can
     * show up on both page 1 and page 2 while another shows up on neither.
     * The id is unique, so it settles every tie the same way each time.
     */
    private function applyOrder(QueryBuilder $qb, TicketCriteria $criteria): void
    {
        $direction = $criteria->direction->toDql();

        foreach ($this->orderExpressions($criteria->sort) as $expression) {
            $qb->addOrderBy($expression, $direction);
        }

        $qb->addOrderBy('t.id', 'DESC');
    }

    /**
     * @return list<string>
     */
    private function orderExpressions(TicketSort $sort): array
    {
        return match ($sort) {
            // A key is a project and a number, and the number is an integer:
            // ordering on the rendered key would put TSU-10 before TSU-9.
            TicketSort::Key => ['p.key', 't.number'],
            TicketSort::Title => ['t.title'],
            TicketSort::Type => [$this->declarationOrder('t.type', TicketType::values())],
            TicketSort::Status => [$this->declarationOrder('t.status', TicketStatus::values())],
            TicketSort::Updated => ['t.updatedAt'],
        };
    }

    /**
     * Orders an enum column by the order its cases are written in, not by the
     * string stored in the column.
     *
     * Alphabetically, a status column reads "blocked, done, in progress,
     * todo" — four statuses in an order that means nothing to anyone, and the
     * order changes again in every other language the interface is served in.
     * The cases are declared in the order the work actually moves through, so
     * that declaration is the ranking, and this turns it into one the database
     * can sort on.
     *
     * @param list<string> $values
     */
    private function declarationOrder(string $field, array $values): string
    {
        $cases = '';

        foreach ($values as $rank => $value) {
            // The values come from the enum's own cases, never from a request.
            $cases .= sprintf(" WHEN '%s' THEN %d", $value, $rank);
        }

        return sprintf('CASE %s%s ELSE %d END', $field, $cases, count($values));
    }

    private function createCriteriaQueryBuilder(TicketCriteria $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('p')
            ->join('t.project', 'p')
        ;

        if (null !== $criteria->projectKey) {
            $qb->andWhere('p.key = :projectKey')->setParameter('projectKey', $criteria->projectKey);
        }

        if ([] !== $criteria->statuses) {
            $qb
                ->andWhere('t.status IN (:statuses)')
                ->setParameter('statuses', array_map(
                    static fn (TicketStatus $status): string => $status->value,
                    $criteria->statuses,
                ))
            ;
        }

        if (null !== $criteria->type) {
            $qb->andWhere('t.type = :type')->setParameter('type', $criteria->type->value);
        }

        if (null !== $criteria->resolution) {
            $qb->andWhere('t.resolution = :resolution')->setParameter('resolution', $criteria->resolution->value);
        }

        if (null !== $criteria->updatedSince) {
            $qb->andWhere('t.updatedAt >= :updatedSince')->setParameter('updatedSince', $criteria->updatedSince);
        }

        $this->applySearch($qb, $criteria);

        return $qb;
    }

    /**
     * Full-text over the ticket itself and over its comments — a design decision
     * recorded in a comment is often the only place a keyword appears.
     */
    private function applySearch(QueryBuilder $qb, TicketCriteria $criteria): void
    {
        $search = $criteria->search;

        if (null === $search || $search->isEmpty()) {
            return;
        }

        if ($search->isIndexable()) {
            $qb
                // orX() is what puts the parentheses in: a raw "A OR B" string
                // would bind as "… AND A OR B" and quietly widen the result set.
                ->andWhere($qb->expr()->orX(
                    'MATCH_AGAINST(t.title, t.description, :fullText) > 0',
                    sprintf(
                        'EXISTS (SELECT ftc.id FROM %s ftc WHERE ftc.ticket = t AND MATCH_AGAINST(ftc.body, :fullText) > 0)',
                        Comment::class,
                    ),
                ))
                ->setParameter('fullText', $search->toBooleanExpression())
            ;

            return;
        }

        // Below innodb_ft_min_token_size the index knows nothing of the term.
        $qb
            ->andWhere($qb->expr()->orX(
                't.title LIKE :likeTerm',
                't.description LIKE :likeTerm',
                sprintf(
                    'EXISTS (SELECT likec.id FROM %s likec WHERE likec.ticket = t AND likec.body LIKE :likeTerm)',
                    Comment::class,
                ),
            ))
            ->setParameter('likeTerm', $search->toLikePattern())
        ;
    }
}
