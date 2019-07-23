<?php

namespace App\Repository;

use App\Entity\Archetype;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Archetype|null find($id, $lockMode = null, $lockVersion = null)
 * @method Archetype|null findOneBy(array $criteria, array $orderBy = null)
 * @method Archetype[]    findAll()
 * @method Archetype[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArchetypeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Archetype::class);
    }

    // /**
    //  * @return Archetype[] Returns an array of Archetype objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Archetype
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
