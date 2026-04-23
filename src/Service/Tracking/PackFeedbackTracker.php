<?php

namespace App\Service\Tracking;

use App\Entity\Pack;
use App\Entity\UserApp;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class PackFeedbackTracker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function track(?UserApp $user, Pack $pack, string $action, array $meta = []): void
    {
        if (!$user instanceof UserApp || !$pack->getIdPack()) {
            return;
        }

        try {
            $this->connection->insert('feedback_event', [
                'user_id' => $user->getId_user(),
                'pack_id' => $pack->getIdPack(),
                'action' => $action,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'meta_json' => $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Pack feedback tracking skipped.', [
                'action' => $action,
                'pack_id' => $pack->getIdPack(),
                'user_id' => $user->getId_user(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
