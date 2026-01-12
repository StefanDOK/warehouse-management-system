<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PickList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PickList>
 */
class PickListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickList::class);
    }

    public function save(PickList $pickList, bool $flush = false): void
    {
        $this->getEntityManager()->persist($pickList);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PickList $pickList, bool $flush = false): void
    {
        $this->getEntityManager()->remove($pickList);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPickListNumber(string $pickListNumber): ?PickList
    {
        return $this->findOneBy(['pickListNumber' => $pickListNumber]);
    }

    /**
     * @return PickList[]
     */
    public function findPending(): array
    {
        return $this->findBy(['status' => PickList::STATUS_PENDING], ['createdAt' => 'ASC']);
    }

    /**
     * @return PickList[]
     */
    public function findInProgress(): array
    {
        return $this->findBy(['status' => PickList::STATUS_IN_PROGRESS], ['startedAt' => 'ASC']);
    }

    /**
     * @return PickList[]
     */
    public function findCompletedToday(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->createQueryBuilder('pl')
            ->where('pl.status = :status')
            ->andWhere('pl.completedAt >= :today')
            ->andWhere('pl.completedAt < :tomorrow')
            ->setParameter('status', PickList::STATUS_COMPLETED)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('pl.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

