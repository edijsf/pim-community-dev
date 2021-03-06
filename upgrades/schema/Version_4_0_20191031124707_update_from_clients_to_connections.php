<?php declare(strict_types=1);

namespace Pim\Upgrade\Schema;

use Akeneo\Connectivity\Connection\Application\Settings\Command\CreateConnectionCommand;
use Akeneo\Connectivity\Connection\Application\Settings\Command\CreateConnectionHandler;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Version_4_0_20191031124707_update_from_clients_to_connections
    extends AbstractMigration
    implements ContainerAwareInterface
{
    /** @var ContainerInterface */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('SELECT "disable migration warning"');

        $this->migrateToConnections();
    }

    public function down(Schema $schema) : void
    {
        $this->throwIrreversibleMigrationException();
    }

    private function migrateToConnections(): void
    {
        $selectClients = <<< SQL
    SELECT id, label
    FROM pim_api_client;
SQL;
        $clientsStatement = $this->dbalConnection()->executeQuery($selectClients);
        $clients = $clientsStatement->fetchAll();

        $this->skipIf(empty($clients), 'No API connection to migrate.');
        $this->write(sprintf('%s API connections found. They will be migrate to Connection.', count($clients)));

        $clients = $this->generateConnectionsCode($clients);

        foreach ($clients as $client) {
            $command = new CreateConnectionCommand(
                $client['code'],
                substr($client['label'], 0, 97),
                FlowType::OTHER
            );

            $this->createConnectionHandler()->handle($command);

            $clientToDelete = $this->retrieveAutoGeneratedClientId($client['code']);

            $this->updateConnectionWithOldClient($client['code'], $client['id']);

            $this->deleteAutoGeneratedClient($clientToDelete);
        }

        $count = $this->countCreatedConnections();
        $this->write(sprintf('%s Connections created.', $count));
    }

    private function countCreatedConnections(): int
    {
        $countConnectionsQuery = <<< SQL
SELECT count(code)
FROM akeneo_app
SQL;
        $countConnections = $this->dbalConnection()->executeQuery($countConnectionsQuery);

        return (int) $countConnections->fetchColumn();
    }

    /**
     * The Connection has been created with an auto generated client.
     *
     * @param string $code
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function retrieveAutoGeneratedClientId(string $code)
    {
        $retrieveNewConnectionClientId = <<< SQL
SELECT client_id
FROM akeneo_app
WHERE code = :code
SQL;
        $retrieveStatement = $this->dbalConnection()->executeQuery($retrieveNewConnectionClientId, ['code' => $code]);

        return $retrieveStatement->fetchColumn();
    }

    /**
     * Connection has been created with an auto generated client. The Connection need to be updated with the old client id.
     *
     * @param string $code
     * @param string $clientId
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updateConnectionWithOldClient(string $code, string $clientId): void
    {
        $updateConnectionQuery = <<< SQL
UPDATE akeneo_app
SET client_id = :client_id
WHERE code = :code;
SQL;
        $this->dbalConnection()->executeQuery(
            $updateConnectionQuery,
            [
                'code' => $code,
                'client_id' => $clientId,
            ]
        );
    }

    /**
     * Once the Connection is updated with old client id we remove the auto generated client.
     *
     * @param string $clientId
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function deleteAutoGeneratedClient(string $clientId): void
    {
        $deleteClientQuery = <<< SQL
DELETE from pim_api_client WHERE id = :client_id;
SQL;
        $this->dbalConnection()->executeQuery($deleteClientQuery, ['client_id' => $clientId]);
    }

    /**
     * Generate the code of the Connection in terms of the label of the client.
     * If several Connection have the same code, it auto increments codes.
     *
     * @param array $clients
     *
     * @return array
     */
    private function generateConnectionsCode(array $clients): array
    {
        array_walk($clients, function (&$client) {
            $client['code'] = $this->slugify($client['label']);
        });

        return $this->makeConnectionsCodeUnique($clients);
    }

    /**
     * Auto increments Connections code if needed.
     *
     * @param array $clients
     *
     * @return array
     */
    private function makeConnectionsCodeUnique(array $clients): array
    {
        $codeOccurence = array_count_values(array_column($clients, 'code'));
        foreach ($clients as $index => $client) {
            $code = $client['code'];

            if (1 < $codeOccurence[$code]) {
                $clients[$index]['code'] = sprintf('%s_%s', $code, (string) $codeOccurence[$code]);
                $codeOccurence[$code]--;
            }
        }

        return $clients;
    }

    private function dbalConnection(): DbalConnection
    {
        return $this->container->get('database_connection');
    }

    private function createConnectionHandler(): CreateConnectionHandler
    {
        return $this->container->get('akeneo_connectivity.connection.application.handler.create_connection');
    }

    private function slugify(string $label): string
    {
        $truncated = substr($label, 0, 97);

        return preg_replace('/[^A-Za-z0-9\-]/', '_', $truncated);
    }
}
