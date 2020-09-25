<?php

declare(strict_types=1);

namespace EHR\Auth\CheckInSession\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FeatureTogglesSyncCommand extends Command
{
    private Connection $db;

    private const TABLE = 'feature_toggles';

    public function __construct(
        Connection $db
    ) {
        parent::__construct('featuretoggle:sync');

        $this->db = $db;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pathToData = '/feature_toggles.json';
        $theirPracticeId = 2791;
        $myPracticeId = 311;
        $theirOrganizationId = '65523061-2c69-413a-946e-561037039d6e';
        $myOrganizationId = 'd9daef01-b9f1-4202-8456-7392af171b72';

        $table = self::TABLE;
        $theirData = $this->decode(file_get_contents($pathToData));
        $myData = $this->getData();

        $sql = <<<SQL
            update {$table} 
            set 
                by_default = :by_default,
                enabled_for_practices = :enabled_for_practices,
                enabled_for_organizations = :enabled_for_organizations,
                disabled_for_practices = :disabled_for_practices,
                disabled_for_organizations = :disabled_for_organizations
             where name = :name
SQL;

        foreach ($theirData as $theirTuple)
        {
            $myTuple = $myData[$theirTuple['name']];

            $myEnabledForPractices = $this->decode($myTuple['enabled_for_practices']);
            $myEnabledForOrganizations = $this->decode($myTuple['enabled_for_organizations']);
            $myDisabledForPractices = $this->decode($myTuple['disabled_for_practices']);
            $myDisabledForOrganizations = $this->decode($myTuple['disabled_for_organizations']);

            $theirEnabledForPractices = $this->decode($theirTuple['enabled_for_practices']);
            $theirEnabledForOrganizations = $this->decode($theirTuple['enabled_for_organizations']);
            $theirDisabledForPractices = $this->decode($theirTuple['disabled_for_practices']);
            $theirDisabledForOrganizations = $this->decode($theirTuple['disabled_for_organizations']);

            $myEnabledForPractices = $this->mergeValues($theirPracticeId, $theirEnabledForPractices, $myPracticeId, $myEnabledForPractices);
            $myDisabledForPractices = $this->mergeValues($theirPracticeId, $theirDisabledForPractices, $myPracticeId, $myDisabledForPractices);
            $myEnabledForOrganizations = $this->mergeValues($theirOrganizationId, $theirEnabledForOrganizations, $myOrganizationId, $myEnabledForOrganizations);
            $myDisabledForOrganizations = $this->mergeValues($theirOrganizationId, $theirDisabledForOrganizations, $myOrganizationId, $myDisabledForOrganizations);

            $this->db->executeUpdate(
                $sql,
                [
                    'by_default' => $theirTuple['by_default'],
                    'enabled_for_practices' => $myEnabledForPractices,
                    'enabled_for_organizations' => $myEnabledForOrganizations,
                    'disabled_for_practices' => $myDisabledForPractices,
                    'disabled_for_organizations' => $myDisabledForOrganizations,
                    'name' => $theirTuple['name'],
                ],
                [
                    'by_default' => Types::BOOLEAN,
                    'enabled_for_practices' => Types::JSON,
                    'enabled_for_organizations' => Types::JSON,
                    'disabled_for_practices' => Types::JSON,
                    'disabled_for_organizations' => Types::JSON,
                    'name' => Types::STRING,
                ]
            );
        }
        return 0;
    }

    private function decode(string $d): array
    {
        return json_decode($d, true,JSON_THROW_ON_ERROR);
    }

    private function mergeValues($theirId, array $theirColumnIds, $myId, array $myColumnIds): array
    {
        if (in_array($theirId, $theirColumnIds, true)) {
            return array_unique(array_merge($myColumnIds, [$myId]));
        }
        return array_diff($myColumnIds, [$myId]);
    }

    private function getData(): array
    {
        $sql = <<<SQL
            select * from feature_toggles
SQL;
        $query = $this->db->executeQuery($sql);
        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[$item['name']] = $item;
        }

        return $result;
    }
}
