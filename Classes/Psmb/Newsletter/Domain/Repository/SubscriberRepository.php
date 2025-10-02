<?php
namespace Psmb\Newsletter\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Persistence\Repository;
use Psmb\Newsletter\Domain\Model\Filter;

/**
 * @Flow\Scope("singleton")
 * @method int countByEmail(string $email)
 * @method QueryResultInterface findByEmail(string $email)
 */
class SubscriberRepository extends Repository
{
    public function findBySubscriptionId(string $subscriptionId): QueryResultInterface
    {
        $query = $this->createQuery();
        return $query->matching(
            $query->like('subscriptions', '%"' . $subscriptionId . '"%')
        )->execute();
    }

    public function findAllByFilter(Filter $filter): QueryResultInterface
    {
        $query = $this->createQuery();
        $constraint = [];

        if (!empty($filter->getName())) {
            $constraint[] = $query->like('name', '%' . $filter->getName() . '%');
        }
        if (!empty($filter->getEmail())) {
            $constraint[] = $query->like('email', '%' . $filter->getEmail() . '%');
        }
        if (!empty($filter->getSubscriptions())) {
            $subs = [];

            foreach ($filter->getSubscriptions() as $subscription) {
                $subs[] = $query->like('subscriptions', '%"' . $subscription . '"%');
            }
            $constraint[] = $query->logicalAnd($subs);
        }
        if (empty($constraint)) {
            return $this->findAll();
        }

        return $query->matching($query->logicalAnd(
            $constraint
        ))->execute();
    }
}
